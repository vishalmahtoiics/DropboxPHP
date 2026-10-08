<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Sanitizer;
use App\Core\ValidationException;
use Throwable;

/**
 * The single entry point for every lead on the site.
 *
 *   honeypot filled?          → report success, store nothing
 *   token bad / too fast?      → reject
 *   more than 5 in 10 minutes from one address → reject
 *   rebuild validation from the stored form fields (never trust the client)
 *   create Lead + FormSubmission + consent record, with first/last-touch UTMs
 *   send notifications after the response (never blocks the visitor)
 */
final class LeadCapture
{
    /** @var list<callable> work to run after the response is sent */
    private static array $deferred = [];

    /**
     * @param array<string, mixed> $input the POST body
     * @return array{ok:bool, message?:string, redirect?:?string, download?:?string, error?:string, fieldErrors?:array<string,string>, leadId?:?int}
     */
    public static function submit(Request $request, array $input): array
    {
        $slug = is_string($input['form'] ?? null) ? $input['form'] : '';
        $form = Forms::bySlug($slug);
        if (!$form) {
            return ['ok' => false, 'error' => 'This form is no longer available.'];
        }

        // Honeypot: a bot that fills every field is told it succeeded.
        if (trim((string) ($input['website_url'] ?? '')) !== '') {
            return ['ok' => true, 'message' => (string) $form['success_message'], 'redirect' => null];
        }

        $check = Forms::checkToken((int) $form['id'], (string) ($input['token'] ?? ''));
        if ($check === 'fast') {
            return ['ok' => false, 'error' => 'That was quick! Please take a moment and submit again.'];
        }
        if ($check !== 'ok') {
            return ['ok' => false, 'error' => 'This form has expired. Please reload the page and try again.'];
        }

        $ip = $request->ip();
        if (!RateLimiter::hit('form:' . $ip, 5, 600)) {
            return ['ok' => false, 'error' => 'Too many submissions from your network. Please try again in a few minutes.'];
        }

        if ((int) $form['require_captcha'] === 1
            && !Captcha::verify((int) $form['id'], (string) ($input['captcha_token'] ?? ''), (string) ($input['captcha_answer'] ?? ''))) {
            return ['ok' => false, 'error' => 'Please answer the security question correctly.', 'fieldErrors' => ['captcha' => 'That answer is not right.']];
        }

        try {
            $values = self::validate($form, is_array($input['fields'] ?? null) ? $input['fields'] : []);
        } catch (ValidationException $e) {
            return ['ok' => false, 'error' => 'Please correct the highlighted fields.', 'fieldErrors' => $e->errors];
        }

        $consentIn = is_array($input['consent'] ?? null) ? $input['consent'] : [];
        $consent = [
            'enquiry' => !empty($consentIn['enquiry']),
            'marketing' => !empty($consentIn['marketing']),
            'terms' => !empty($consentIn['terms']),
        ];
        if ($form['consent_text'] && (int) $form['creates_lead'] === 1 && !$consent['enquiry']) {
            return ['ok' => false, 'error' => 'Please confirm you agree to be contacted.', 'fieldErrors' => ['consent' => 'Required.']];
        }
        if ((int) $form['require_terms_acceptance'] === 1 && !$consent['terms']) {
            return ['ok' => false, 'error' => 'Please accept the terms to continue.'];
        }

        [$country, $local] = Countries::resolve((string) (parse_url((string) ($input['page_url'] ?? ''), PHP_URL_PATH) ?: '/'));
        $pageUrl = mb_substr(Sanitizer::url((string) ($input['page_url'] ?? '')), 0, 1000) ?: null;
        $pageQuery = [];
        parse_str((string) parse_url((string) $pageUrl, PHP_URL_QUERY), $pageQuery);
        $attr = Attribution::forLead($request, $pageQuery);
        $productId = self::productId($form, $input, $values);

        $lead = self::leadCore($form, $values);
        $result = DB::transaction(static function () use ($form, $values, $lead, $country, $local, $pageUrl, $attr, $request, $input, $productId, $consent, $ip): array {
            $leadId = null;
            if ((int) $form['creates_lead'] === 1 && $lead['email'] !== '') {
                $page = DB::first('SELECT id FROM pages WHERE country_id = ? AND slug = ? AND deleted_at IS NULL', [$country['id'], $local]);
                $first = $attr['first'];
                $last = $attr['last'];
                $leadId = DB::insert('leads', [
                    'country_id' => $country['id'],
                    'name' => $lead['name'] ?: $lead['email'],
                    'email' => $lead['email'],
                    'phone' => $lead['phone'] ?: null,
                    'company' => $lead['company'] ?: null,
                    'job_title' => $lead['job_title'] ?: null,
                    'message' => $lead['message'] ?: null,
                    'source' => $form['lead_source'] ?: 'Website Form',
                    'campaign' => $last['utm_campaign'] ?? null,
                    'cta_label' => mb_substr(Sanitizer::line((string) ($input['cta_label'] ?? '')), 0, 160) ?: null,
                    'cta_location' => mb_substr(Sanitizer::line((string) ($input['cta_location'] ?? '')), 0, 160) ?: null,
                    'utm_source' => $last['utm_source'] ?? null,
                    'utm_medium' => $last['utm_medium'] ?? null,
                    'utm_campaign' => $last['utm_campaign'] ?? null,
                    'utm_term' => $last['utm_term'] ?? null,
                    'utm_content' => $last['utm_content'] ?? null,
                    'first_utm_source' => $first['utm_source'] ?? null,
                    'first_utm_medium' => $first['utm_medium'] ?? null,
                    'first_utm_campaign' => $first['utm_campaign'] ?? null,
                    'first_utm_term' => $first['utm_term'] ?? null,
                    'first_utm_content' => $first['utm_content'] ?? null,
                    'first_landing_url' => $first['landing'] ?? null,
                    'first_touch_at' => isset($first['at']) && strtotime($first['at']) ? date('Y-m-d H:i:s', strtotime($first['at'])) : null,
                    'referrer' => $last['referrer'] ?? (mb_substr((string) $request->referer(), 0, 1000) ?: null),
                    'landing_url' => $pageUrl,
                    'user_agent' => $request->userAgent(),
                    'ip_hash' => Crypto::hash($ip, 'ip'),
                    'product_id' => $productId,
                    'landing_page_id' => $page['id'] ?? null,
                    'form_id' => $form['id'],
                    'lead_magnet_id' => !empty($input['magnet']) ? (DB::value('SELECT id FROM lead_magnets WHERE slug = ? AND deleted_at IS NULL', [(string) $input['magnet']]) ?: null) : null,
                    'marketing_consent' => $consent['marketing'] ? 1 : 0,
                    'pipeline_order' => 0,
                ]);
                DB::insert('lead_activities', [
                    'lead_id' => $leadId,
                    'type' => 'CREATED',
                    'summary' => 'Lead created from the "' . $form['name'] . '" form' . (!empty($input['cta_location']) ? ' (' . Sanitizer::line((string) $input['cta_location'], 120) . ')' : ''),
                ]);
                self::autoAssign($leadId);
            }
            $labels = [];
            foreach ($form['fields'] as $f) {
                $labels[$f['name']] = $f['label'];
            }
            $submissionId = DB::insert('form_submissions', [
                'form_id' => $form['id'],
                'country_id' => $country['id'],
                'lead_id' => $leadId,
                'data' => $values,
                'field_labels' => $labels,
                'form_name' => $form['name'],
                'ip_hash' => Crypto::hash($ip, 'ip'),
                'user_agent' => $request->userAgent(),
                'referrer' => mb_substr((string) $request->referer(), 0, 1000) ?: null,
                'page_url' => $pageUrl,
            ]);
            if ($consent['enquiry'] || $consent['marketing'] || $consent['terms']) {
                DB::insert('consent_records', [
                    'lead_id' => $leadId,
                    'submission_id' => $submissionId,
                    'country_id' => $country['id'],
                    'email' => $lead['email'] ?: null,
                    'enquiry_consent' => $consent['enquiry'] ? 1 : 0,
                    'marketing_consent' => $consent['marketing'] ? 1 : 0,
                    'terms_accepted' => $consent['terms'] ? 1 : 0,
                    'lawful_basis' => $form['lawful_basis'],
                    'notice_text' => $form['consent_text'],
                    'page_url' => $pageUrl,
                    'ip_hash' => Crypto::hash($ip, 'ip'),
                ]);
            }
            return ['leadId' => $leadId, 'submissionId' => $submissionId];
        });

        self::$deferred[] = static fn () => self::notify($form, $values, $lead, $result['leadId'], $pageUrl, $productId);

        $download = null;
        $magnetSlug = (string) ($input['magnet'] ?? '');
        if ($magnetSlug !== '') {
            $download = '/lead-magnet/' . rawurlencode($magnetSlug) . '/download?t=' . urlencode(Crypto::hash($magnetSlug . '|' . date('Y-m-d'), 'magnet', 24));
        }

        return [
            'ok' => true,
            'message' => (string) $form['success_message'],
            'redirect' => $form['redirect_url'] ? Sanitizer::url((string) $form['redirect_url']) : null,
            'download' => $download,
            'leadId' => $result['leadId'],
        ];
    }

    /**
     * Rebuild the rules from the stored field definitions and apply them.
     * A tampered payload cannot relax a required field or add an option.
     *
     * @param array<string, mixed> $raw
     * @return array<string, string|list<string>>
     */
    public static function validate(array $form, array $raw): array
    {
        $values = [];
        $errors = [];
        foreach ($form['fields'] as $f) {
            $name = $f['name'];
            $type = $f['type'];
            $label = $f['label'];
            $required = (int) $f['is_required'] === 1;
            $value = $raw[$name] ?? null;

            if ($type === 'HIDDEN') {
                $values[$name] = (string) ($f['default_value'] ?? ''); // never from the client
                continue;
            }
            if ($type === 'MULTISELECT') {
                $chosen = array_values(array_intersect(array_map('strval', is_array($value) ? $value : []), $f['options']));
                if ($required && $chosen === []) {
                    $errors[$name] = "Choose at least one option.";
                }
                $values[$name] = $chosen;
                continue;
            }
            if ($type === 'CHECKBOX' || $type === 'CONSENT') {
                $checked = in_array($value, ['1', 'on', 'true', 1, true], true);
                if ($required && !$checked) {
                    $errors[$name] = 'Please tick this box to continue.';
                }
                $values[$name] = $checked ? 'Yes' : 'No';
                continue;
            }
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($value === '') {
                if ($required) {
                    $errors[$name] = "$label is required.";
                }
                $values[$name] = '';
                continue;
            }
            $max = (int) ($f['max_length'] ?: ($type === 'TEXTAREA' ? 5000 : 255));
            $min = (int) ($f['min_length'] ?: 0);
            if (mb_strlen($value) > $max) {
                $errors[$name] = "$label must be at most $max characters.";
                continue;
            }
            if ($min && mb_strlen($value) < $min) {
                $errors[$name] = "$label must be at least $min characters.";
                continue;
            }
            $error = match ($type) {
                'EMAIL' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email address.',
                'PHONE' => preg_match('/^[+()\d\s.\-]{6,25}$/', $value) && preg_match_all('/\d/', $value) >= 6 ? null : 'Enter a valid phone number.',
                'NUMBER' => is_numeric($value) ? null : "$label must be a number.",
                'URL' => preg_match('#^(https?://)?[a-z0-9.\-]+\.[a-z]{2,}(/\S*)?$#i', $value) ? null : 'Enter a valid web address.',
                'DATE' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? null : 'Enter a valid date.',
                'TIME' => preg_match('/^\d{2}:\d{2}$/', $value) ? null : 'Enter a valid time.',
                'SELECT', 'RADIO' => in_array($value, $f['options'], true) ? null : 'Choose one of the options.',
                default => null,
            };
            if ($error === null && $f['pattern'] && @preg_match('#^(?:' . str_replace('#', '\#', (string) $f['pattern']) . ')$#u', $value) !== 1) {
                $error = "$label has an invalid format.";
            }
            if ($error !== null) {
                $errors[$name] = $error;
                continue;
            }
            $values[$name] = $type === 'TEXTAREA' ? Sanitizer::text($value, $max) : Sanitizer::line($value, $max);
            if ($type === 'EMAIL') {
                $values[$name] = strtolower($values[$name]);
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return $values;
    }

    /** Map typed fields onto the lead's columns. @return array<string,string> */
    private static function leadCore(array $form, array $values): array
    {
        $core = ['name' => '', 'email' => '', 'phone' => '', 'company' => '', 'job_title' => '', 'message' => ''];
        $extra = [];
        foreach ($form['fields'] as $f) {
            $v = $values[$f['name']] ?? '';
            $v = is_array($v) ? implode(', ', $v) : (string) $v;
            if ($v === '') {
                continue;
            }
            $target = match ($f['type']) {
                'NAME' => 'name', 'EMAIL' => 'email', 'PHONE' => 'phone', 'COMPANY' => 'company',
                default => match ($f['name']) {
                    'name', 'full_name' => 'name', 'email' => 'email', 'phone', 'mobile' => 'phone', 'company', 'organisation', 'organization' => 'company',
                    'job_title', 'title', 'designation' => 'job_title', 'message', 'comments', 'notes' => 'message',
                    default => null,
                },
            };
            if ($target !== null && $core[$target] === '') {
                $core[$target] = $v;
            } elseif (!in_array($f['type'], ['CONSENT', 'HIDDEN'], true)) {
                $extra[] = $f['label'] . ': ' . $v;
            }
        }
        if ($core['name'] === '' && isset($values['first_name'])) {
            $core['name'] = trim($values['first_name'] . ' ' . ($values['last_name'] ?? ''));
        }
        if ($extra) {
            $core['message'] = trim($core['message'] . "\n\n" . implode("\n", $extra));
        }
        return array_map(static fn ($v) => mb_substr($v, 0, 5000), $core);
    }

    /**
     * Which product the enquiry is about. The client's product id is only
     * believed for a published product; otherwise the form's default.
     */
    private static function productId(array $form, array $input, array $values): ?int
    {
        $id = (int) ($input['product_id'] ?? 0);
        if ($id > 0 && DB::value("SELECT id FROM products WHERE id = ? AND deleted_at IS NULL AND status = 'PUBLISHED'", [$id])) {
            return $id;
        }
        return $form['default_product_id'] ? (int) $form['default_product_id'] : null;
    }

    /** Round-robin assignment to active Sales users when enabled. */
    private static function autoAssign(int $leadId): void
    {
        if (!Settings::get('crm', 'auto_assign', false)) {
            return;
        }
    }

    private static function notify(array $form, array $values, array $lead, ?int $leadId, ?string $pageUrl, ?int $productId): void
    {
        $email = Settings::group('email');
        $product = $productId ? (string) DB::value('SELECT name FROM products WHERE id = ?', [$productId]) : '';
        $leadUrl = $leadId ? absolute_url('/admin/leads/' . $leadId) : '';
        $vars = [
            'lead.name' => $lead['name'], 'lead.email' => $lead['email'], 'lead.phone' => $lead['phone'],
            'lead.company' => $lead['company'], 'lead.product' => $product, 'lead.source' => $form['lead_source'],
            'lead.message' => $lead['message'], 'lead.url' => $leadUrl, 'lead.companySuffix' => $lead['company'] ? ' (' . $lead['company'] . ')' : '',
            'form.name' => $form['name'], 'page.url' => (string) $pageUrl,
        ];
        if ($leadId && $email['notify_new_lead']) {
            $to = array_filter(array_map('trim', explode(',', (string) $email['sales_emails'])));
            if ($to && ($msg = EmailTemplates::render('new-lead', $vars))) {
                Mailer::send($to, $msg['subject'], $msg['html'], $lead['email'] ?: null);
            }
        }
        $formTo = array_filter(array_map('trim', explode(',', (string) $form['notify_emails'])));
        if ($formTo || ($email['notify_submission'] && !$leadId)) {
            $rows = '';
            foreach ($form['fields'] as $f) {
                $v = $values[$f['name']] ?? '';
                $rows .= '<tr><td style="padding:6px;border-bottom:1px solid #eee"><b>' . e($f['label']) . '</b></td><td style="padding:6px;border-bottom:1px solid #eee">' . nl2br(e(is_array($v) ? implode(', ', $v) : $v)) . '</td></tr>';
            }
            $vars['submission.table'] = '<table cellpadding="0" cellspacing="0" width="100%">' . $rows . '</table>';
            $to = $formTo ?: array_filter(array_map('trim', explode(',', (string) $email['sales_emails'])));
            if ($to && ($msg = EmailTemplates::render('form-submission', $vars))) {
                Mailer::send($to, $msg['subject'], $msg['html'], $lead['email'] ?: null);
            }
        }
        if ($leadId && $email['send_confirmation'] && $lead['email'] !== '' && ($msg = EmailTemplates::render('lead-confirmation', $vars))) {
            Mailer::send($lead['email'], $msg['subject'], $msg['html']);
        }
    }

    /** Run notifications once the visitor already has their response. */
    public static function runDeferred(): void
    {
        $jobs = self::$deferred;
        self::$deferred = [];
        foreach ($jobs as $job) {
            try {
                $job();
            } catch (Throwable $e) {
                ErrorHandler::report($e);
            }
        }
    }
}
