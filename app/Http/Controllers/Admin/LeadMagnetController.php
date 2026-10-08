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
use App\Services\Options;
use App\Services\Slugs;

final class LeadMagnetController extends AdminController
{
    public const KINDS = ['PDF' => 'PDF', 'EBOOK' => 'E-book', 'GUIDE' => 'Guide', 'CHECKLIST' => 'Checklist', 'WHITEPAPER' => 'Whitepaper', 'DEMO' => 'Demo', 'CONSULTATION' => 'Consultation', 'OFFER' => 'Offer'];

    public function index(): Response
    {
        $this->authorize('marketing.manage');
        $rows = DB::all('SELECT lm.*, f.name AS form_name, (SELECT COUNT(*) FROM leads l WHERE l.lead_magnet_id = lm.id) AS leads FROM lead_magnets lm LEFT JOIN forms f ON f.id = lm.form_id WHERE lm.deleted_at IS NULL ORDER BY lm.title');
        return $this->screen('marketing/magnets', ['title' => 'Lead magnets', 'rows' => $rows]);
    }

    public function create(): Response
    {
        $this->authorize('marketing.manage');
        return $this->editor(null);
    }

    public function edit(string $id): Response
    {
        $this->authorize('marketing.manage');
        return $this->editor($this->find($this->id($id)));
    }

    private function editor(?array $m): Response
    {
        $url = static fn ($id) => $id ? (string) DB::value('SELECT url FROM media WHERE id = ?', [$id]) : '';
        return $this->screen('marketing/magnet-edit', ['title' => $m ? $m['title'] : 'New lead magnet', 'm' => $m, 'forms' => Options::formsById(),
            'imageUrl' => $m ? $url($m['image_id']) : '', 'fileUrl' => $m ? $url($m['file_id']) : '', 'narrow' => true]);
    }

    public function store(): Response
    {
        $this->authorize('marketing.manage');
        $id = DB::insert('lead_magnets', $this->data(null));
        Audit::record('created', 'LeadMagnet', $id);
        $this->flash('Lead magnet created. Add it to a page with the Lead magnet section.');
        return $this->redirect('/admin/lead-magnets/' . $id);
    }

    public function update(string $id): Response
    {
        $this->authorize('marketing.manage');
        $m = $this->find($this->id($id));
        DB::update('lead_magnets', $this->data((int) $m['id']), ['id' => $m['id']]);
        Audit::record('updated', 'LeadMagnet', $m['id']);
        $this->flash('Saved.');
        return $this->redirect('/admin/lead-magnets/' . $m['id']);
    }

    /** @return array<string,mixed> */
    private function data(?int $id): array
    {
        $d = $this->validate(['title' => 'required|max:200', 'slug' => 'nullable|max:160', 'kind' => 'required|in:' . implode(',', array_keys(self::KINDS)),
            'description' => 'nullable|max:2000', 'is_active' => 'bool', 'image' => 'nullable|max:1000', 'file' => 'nullable|max:1000',
            'external_url' => 'nullable|absurl|max:500', 'form_id' => 'nullable|int', 'cta_label' => 'required|max:80']);
        $file = $d['file'] ? DB::value('SELECT id FROM media WHERE url = ?', [$d['file']]) : null;
        if (!$file && !$d['external_url'] && !$d['form_id']) {
            throw ValidationException::with('file', 'Choose a file, an external link, or a form.');
        }
        return ['title' => Sanitizer::line((string) $d['title'], 200), 'slug' => Slugs::unique('lead_magnets', Slugs::make((string) ($d['slug'] ?: $d['title'])), $id),
            'kind' => $d['kind'], 'description' => Sanitizer::text((string) $d['description']) ?: null, 'is_active' => $d['is_active'] ? 1 : 0,
            'image_id' => $d['image'] ? (DB::value('SELECT id FROM media WHERE url = ?', [$d['image']]) ?: null) : null, 'file_id' => $file ?: null,
            'external_url' => $d['external_url'] ?: null, 'form_id' => $d['form_id'] ?: null, 'cta_label' => Sanitizer::line((string) $d['cta_label'], 80)];
    }

    public function destroy(string $id): Response
    {
        $this->authorize('marketing.manage');
        $m = $this->find($this->id($id));
        DB::update('lead_magnets', ['deleted_at' => now(), 'is_active' => 0, 'slug' => Slugs::trashed($m['slug'], (int) $m['id'])], ['id' => $m['id']]);
        Audit::record('deleted', 'LeadMagnet', $m['id'], $m['title']);
        $this->flash('Moved to the recycle bin.');
        return $this->redirect('/admin/lead-magnets');
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $m = DB::first('SELECT * FROM lead_magnets WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$m) {
            throw HttpException::notFound();
        }
        return $m;
    }
}
