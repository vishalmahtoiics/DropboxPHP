<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\ValidationException;
use App\Http\AdminController;
use App\Services\Countries;
use App\Services\Forms;
use App\Services\Options;
use App\Services\Slugs;

/** The form builder: fields, validation, consent, notifications and submissions. */
final class FormController extends AdminController
{
    public function index(): Response
    {
        $this->authorize('forms.view');
        $rows = DB::all(
            "SELECT f.*, (SELECT COUNT(*) FROM form_fields ff WHERE ff.form_id = f.id) AS fields,
                    (SELECT COUNT(*) FROM form_submissions s WHERE s.form_id = f.id) AS submissions,
                    (SELECT COUNT(*) FROM form_submissions s WHERE s.form_id = f.id AND s.created_at >= NOW() - INTERVAL 30 DAY) AS recent,
                    c.name AS country_name
               FROM forms f LEFT JOIN countries c ON c.id = f.country_id WHERE f.deleted_at IS NULL ORDER BY f.name"
        );
        return $this->screen('forms/index', ['title' => 'Forms', 'rows' => $rows]);
    }

    public function create(): Response
    {
        $this->authorize('forms.create');
        return $this->editor(null);
    }

    public function edit(string $id): Response
    {
        $this->authorize('forms.view');
        return $this->editor($this->find($this->id($id)));
    }

    private function editor(?array $form): Response
    {
        $fields = $form ? Forms::fields((int) $form['id']) : [
            ['type' => 'NAME', 'label' => 'Full name', 'name' => 'name', 'is_required' => 1, 'width' => 'half'],
            ['type' => 'EMAIL', 'label' => 'Email', 'name' => 'email', 'is_required' => 1, 'width' => 'half'],
            ['type' => 'PHONE', 'label' => 'Phone', 'name' => 'phone', 'is_required' => 0, 'width' => 'half'],
            ['type' => 'COMPANY', 'label' => 'Company', 'name' => 'company', 'is_required' => 0, 'width' => 'half'],
            ['type' => 'TEXTAREA', 'label' => 'Message', 'name' => 'message', 'is_required' => 0, 'width' => 'full'],
        ];
        return $this->screen('forms/edit', [
            'title' => $form ? $form['name'] : 'New form',
            'form' => $form,
            'fields' => $fields,
            'types' => Forms::FIELD_TYPES,
            'products' => Options::products(),
            'markets' => array_column(Countries::accessible(), 'name', 'id'),
            'design' => $form ? (json_decode((string) $form['design'], true) ?: []) : [],
        ]);
    }

    public function store(): Response
    {
        $this->authorize('forms.create');
        $id = $this->save(null);
        $this->flash('Form created. Add it to a page with the Form section, a hero, a popup or a product.');
        return $this->redirect('/admin/forms/' . $id);
    }

    public function update(string $id): Response
    {
        $this->authorize('forms.edit');
        $form = $this->find($this->id($id));
        $this->save($form);
        $this->flash('Form saved.');
        return $this->redirect('/admin/forms/' . $form['id']);
    }

    private function save(?array $existing): int
    {
        $d = $this->validate([
            'name' => 'required|max:160',
            'slug' => 'nullable|max:160',
            'description' => 'nullable|max:1000',
            'is_active' => 'bool',
            'submit_label' => 'required|max:80',
            'success_message' => 'required|max:1000',
            'redirect_url' => 'nullable|url|max:500',
            'lead_source' => 'nullable|max:120',
            'default_product_id' => 'nullable|int',
            'creates_lead' => 'bool',
            'notify_emails' => 'nullable|max:1000',
            'consent_text' => 'nullable|max:2000',
            'lawful_basis' => 'required|in:CONSENT,CONTRACT,LEGITIMATE_INTEREST,LEGAL_OBLIGATION',
            'offer_marketing_consent' => 'bool',
            'require_terms_acceptance' => 'bool',
            'require_captcha' => 'bool',
            'country_id' => 'nullable|int',
            'columns' => 'nullable|in:1,2',
        ]);
        $emails = array_filter(array_map('trim', explode(',', (string) $d['notify_emails'])));
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::with('notify_emails', "\"$email\" is not a valid email address.");
            }
        }
        $slug = Slugs::make((string) ($d['slug'] ?: $d['name']));
        if (DB::value('SELECT id FROM forms WHERE slug = ? AND id <> ?', [$slug, $existing['id'] ?? 0])) {
            throw ValidationException::with('slug', 'Another form uses this key.');
        }
        $fields = $this->fields();
        $row = [
            'name' => Sanitizer::line((string) $d['name'], 160),
            'slug' => $slug,
            'description' => $d['description'] ?: null,
            'is_active' => $d['is_active'] ? 1 : 0,
            'submit_label' => Sanitizer::line((string) $d['submit_label'], 80),
            'success_message' => Sanitizer::text((string) $d['success_message'], 1000),
            'redirect_url' => $d['redirect_url'] ?: null,
            'lead_source' => $d['lead_source'] ?: 'Website Form',
            'default_product_id' => $d['default_product_id'] ?: null,
            'creates_lead' => $d['creates_lead'] ? 1 : 0,
            'notify_emails' => $emails ? implode(', ', $emails) : null,
            'consent_text' => Sanitizer::html((string) $d['consent_text']) ?: null,
            'lawful_basis' => $d['lawful_basis'],
            'offer_marketing_consent' => $d['offer_marketing_consent'] ? 1 : 0,
            'require_terms_acceptance' => $d['require_terms_acceptance'] ? 1 : 0,
            'require_captcha' => $d['require_captcha'] ? 1 : 0,
            'country_id' => $d['country_id'] && in_array((int) $d['country_id'], Countries::accessibleIds(), true) ? $d['country_id'] : null,
            'design' => ['columns' => (int) ($d['columns'] ?: 2)],
        ];
        return DB::transaction(static function () use ($row, $existing, $fields): int {
            if ($existing) {
                DB::update('forms', $row, ['id' => $existing['id']]);
                $id = (int) $existing['id'];
            } else {
                $id = DB::insert('forms', $row);
            }
            // Replace the field set; submissions keep their own label snapshot.
            DB::delete('form_fields', ['form_id' => $id]);
            foreach ($fields as $i => $f) {
                DB::insert('form_fields', $f + ['form_id' => $id, 'sort_order' => $i * 10]);
            }
            Audit::record($existing ? 'updated' : 'created', 'Form', $id, $row['name'] . ' (' . count($fields) . ' fields)');
            Forms::clearCache();
            return $id;
        });
    }

    /** @return list<array<string,mixed>> */
    private function fields(): array
    {
        $out = [];
        $names = [];
        foreach (array_values($this->request->arr('fields')) as $i => $f) {
            if (!is_array($f)) {
                continue;
            }
            $type = (string) ($f['type'] ?? '');
            $label = Sanitizer::line((string) ($f['label'] ?? ''), 200);
            if (!isset(Forms::FIELD_TYPES[$type]) || $label === '') {
                continue;
            }
            $name = strtolower(trim((string) ($f['name'] ?? '')));
            $name = $name !== '' ? preg_replace('/[^a-z0-9_]/', '_', $name) : trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), '_');
            $name = substr((string) $name, 0, 60) ?: 'field_' . $i;
            if (!preg_match('/^[a-z]/', $name)) {
                $name = 'f_' . $name;
            }
            if (isset($names[$name])) {
                throw ValidationException::with('fields', "Two fields share the key \"$name\". Keys must be unique.");
            }
            $names[$name] = true;
            $options = array_values(array_filter(array_map(static fn ($o) => Sanitizer::line($o, 200), preg_split('/\R/', (string) ($f['options'] ?? '')) ?: [])));
            if (in_array($type, ['SELECT', 'RADIO', 'MULTISELECT'], true) && $options === []) {
                throw ValidationException::with('fields', "\"$label\" needs at least one option.");
            }
            $pattern = trim((string) ($f['pattern'] ?? ''));
            if ($pattern !== '' && @preg_match('#^(?:' . str_replace('#', '\#', $pattern) . ')$#u', '') === false) {
                throw ValidationException::with('fields', "The pattern for \"$label\" is not a valid regular expression.");
            }
            $out[] = [
                'type' => $type,
                'label' => $type === 'CONSENT' ? mb_substr(Sanitizer::html((string) ($f['label'] ?? '')), 0, 200) : $label,
                'name' => $name,
                'placeholder' => Sanitizer::line((string) ($f['placeholder'] ?? ''), 200) ?: null,
                'help_text' => Sanitizer::line((string) ($f['help_text'] ?? ''), 500) ?: null,
                'default_value' => Sanitizer::line((string) ($f['default_value'] ?? ''), 500) ?: null,
                'options' => $options ?: null,
                'is_required' => !empty($f['is_required']) ? 1 : 0,
                'is_enabled' => !isset($f['enabled_present']) || !empty($f['is_enabled']) ? 1 : 0,
                'show_label' => !empty($f['show_label']) || !isset($f['show_label_present']) ? 1 : 0,
                'width' => in_array($f['width'] ?? '', ['full', 'half'], true) ? $f['width'] : 'full',
                'min_length' => is_numeric($f['min_length'] ?? null) ? (int) $f['min_length'] : null,
                'max_length' => is_numeric($f['max_length'] ?? null) ? (int) $f['max_length'] : null,
                'pattern' => $pattern ?: null,
            ];
        }
        if ($out === []) {
            throw ValidationException::with('fields', 'A form needs at least one field.');
        }
        return $out;
    }

    public function duplicate(string $id): Response
    {
        $this->authorize('forms.create');
        $form = $this->find($this->id($id));
        $copy = $form;
        unset($copy['id'], $copy['created_at'], $copy['updated_at']);
        $copy['name'] .= ' (copy)';
        $copy['slug'] = Slugs::unique('forms', $form['slug'] . '-copy');
        $newId = DB::insert('forms', $copy);
        foreach (DB::all('SELECT * FROM form_fields WHERE form_id = ?', [$form['id']]) as $f) {
            unset($f['id'], $f['created_at'], $f['updated_at']);
            $f['form_id'] = $newId;
            DB::insert('form_fields', $f);
        }
        Audit::record('duplicated', 'Form', $newId);
        $this->flash('Form duplicated.');
        return $this->redirect('/admin/forms/' . $newId);
    }

    public function destroy(string $id): Response
    {
        $this->authorize('forms.delete');
        $form = $this->find($this->id($id));
        DB::update('forms', ['deleted_at' => now(), 'is_active' => 0, 'slug' => Slugs::trashed($form['slug'], (int) $form['id'])], ['id' => $form['id']]);
        Audit::record('deleted', 'Form', $form['id'], $form['name']);
        $this->flash('Form moved to the recycle bin. Its submissions and leads are kept.');
        return $this->redirect('/admin/forms');
    }

    public function submissions(string $id): Response
    {
        $this->authorize('forms.view');
        $form = $this->find($this->id($id));
        $page = max(1, $this->request->int('page', 1));
        $total = (int) DB::value('SELECT COUNT(*) FROM form_submissions WHERE form_id = ?', [$form['id']]);
        $rows = DB::all('SELECT s.*, c.name AS country_name FROM form_submissions s LEFT JOIN countries c ON c.id = s.country_id WHERE s.form_id = ? ORDER BY s.created_at DESC LIMIT 50 OFFSET ' . (($page - 1) * 50), [$form['id']]);
        if ($this->request->queryStr('export') === 'csv' && \App\Core\Auth::can('leads.export')) {
            return $this->csv($form);
        }
        return $this->screen('forms/submissions', ['title' => $form['name'] . ' submissions', 'form' => $form, 'rows' => $rows, 'total' => $total, 'pageNo' => $page, 'pages' => (int) ceil($total / 50), 'fields' => Forms::fields((int) $form['id'])]);
    }

    private function csv(array $form): Response
    {
        $fields = Forms::fields((int) $form['id']);
        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_merge(['Submitted', 'Lead ID', 'Page'], array_column($fields, 'label')));
        foreach (DB::all('SELECT * FROM form_submissions WHERE form_id = ? ORDER BY created_at DESC', [$form['id']]) as $s) {
            $data = json_decode((string) $s['data'], true) ?: [];
            $row = [$s['created_at'], $s['lead_id'], $s['page_url']];
            foreach ($fields as $f) {
                $v = $data[$f['name']] ?? '';
                $row[] = \App\Services\Csv::safe(is_array($v) ? implode(', ', $v) : (string) $v);
            }
            fputcsv($out, $row);
        }
        rewind($out);
        Audit::record('exported', 'Form', $form['id'], 'Submissions CSV');
        return new Response("\xEF\xBB\xBF" . stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $form['slug'] . '-submissions-' . date('Y-m-d') . '.csv"',
        ]);
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $form = DB::first('SELECT * FROM forms WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$form) {
            throw HttpException::notFound();
        }
        return $form;
    }
}
