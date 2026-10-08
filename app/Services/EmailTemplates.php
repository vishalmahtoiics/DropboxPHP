<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Editable notification templates. A template is HTML with {{tokens}};
 * every token value is HTML-escaped when substituted, so lead data can never
 * inject markup into an email.
 */
final class EmailTemplates
{
    public const DEFAULTS = [
        'new-lead' => [
            'New lead notification (to sales)',
            'New lead: {{lead.name}}{{lead.companySuffix}}',
            '<p>A new lead just arrived.</p><table cellpadding="6"><tr><td><b>Name</b></td><td>{{lead.name}}</td></tr><tr><td><b>Email</b></td><td>{{lead.email}}</td></tr><tr><td><b>Phone</b></td><td>{{lead.phone}}</td></tr><tr><td><b>Company</b></td><td>{{lead.company}}</td></tr><tr><td><b>Product</b></td><td>{{lead.product}}</td></tr><tr><td><b>Source</b></td><td>{{lead.source}}</td></tr><tr><td><b>Message</b></td><td>{{lead.message}}</td></tr></table><p><a href="{{lead.url}}">Open the lead</a></p>',
            ['lead.name', 'lead.email', 'lead.phone', 'lead.company', 'lead.product', 'lead.source', 'lead.message', 'lead.url', 'site.name'],
        ],
        'lead-assigned' => [
            'Lead assigned (to the assignee)',
            'You have been assigned {{lead.name}}',
            '<p>Hi {{user.name}},</p><p>{{actor.name}} assigned you the lead <b>{{lead.name}}</b> ({{lead.email}}).</p><p><a href="{{lead.url}}">Open the lead</a></p>',
            ['user.name', 'actor.name', 'lead.name', 'lead.email', 'lead.url', 'site.name'],
        ],
        'form-submission' => [
            'Form submission (to the form\'s recipients)',
            'New submission: {{form.name}}',
            '<p>New submission to <b>{{form.name}}</b> from {{page.url}}.</p>{{submission.table}}',
            ['form.name', 'page.url', 'submission.table', 'site.name'],
        ],
        'lead-confirmation' => [
            'Confirmation (to the visitor)',
            'We received your request — {{site.name}}',
            '<p>Hi {{lead.name}},</p><p>Thanks for getting in touch. Our team will contact you shortly.</p><p>— {{site.name}}</p>',
            ['lead.name', 'site.name'],
        ],
    ];

    public static function seed(): void
    {
        foreach (self::DEFAULTS as $key => [$name, $subject, $body]) {
            DB::query('INSERT IGNORE INTO email_templates (`key`, name, subject, body) VALUES (?, ?, ?, ?)', [$key, $name, $subject, $body]);
        }
    }

    /**
     * @param array<string, string|null> $vars token => plain value; keys ending in
     *        ".table" are trusted HTML built by the application.
     * @return array{subject:string, html:string}|null
     */
    public static function render(string $key, array $vars): ?array
    {
        $tpl = DB::first('SELECT * FROM email_templates WHERE `key` = ? AND is_active = 1', [$key]);
        if (!$tpl) {
            if (!isset(self::DEFAULTS[$key])) {
                return null;
            }
            $tpl = ['subject' => self::DEFAULTS[$key][1], 'body' => self::DEFAULTS[$key][2]];
        }
        $vars['site.name'] ??= (string) Settings::get('website', 'site_name');
        $subject = preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/', static fn ($m) => str_replace(["\r", "\n"], ' ', (string) ($vars[$m[1]] ?? '')), (string) $tpl['subject']);
        $body = preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/', static function ($m) use ($vars): string {
            $value = (string) ($vars[$m[1]] ?? '');
            return str_ends_with($m[1], '.table') ? $value : nl2br(e($value));
        }, (string) $tpl['body']);
        return ['subject' => mb_substr((string) $subject, 0, 250), 'html' => self::wrap((string) $body)];
    }

    private static function wrap(string $body): string
    {
        $site = Settings::group('website');
        $color = $site['color_primary'] ?: '#0061FF';
        return '<!doctype html><html><body style="margin:0;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#0b1b34">'
            . '<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px">'
            . '<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden">'
            . '<tr><td style="background:' . e($color) . ';color:#fff;padding:16px 24px;font-weight:bold;font-size:16px">' . e($site['site_name']) . '</td></tr>'
            . '<tr><td style="padding:24px;font-size:14px;line-height:1.6">' . $body . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
