<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Http\AdminController;
use App\Services\Countries;
use App\Services\Options;

final class PopupController extends AdminController
{
    public const TYPES = ['LEAD_FORM' => 'Lead form', 'OFFER' => 'Offer', 'NEWSLETTER' => 'Newsletter', 'IMAGE' => 'Image', 'PRODUCT' => 'Product', 'LEAD_MAGNET' => 'Lead magnet'];
    public const TRIGGERS = ['DELAY' => 'After a delay', 'IMMEDIATE' => 'Immediately', 'SCROLL' => 'After scrolling', 'EXIT_INTENT' => 'On exit intent', 'CLICK' => 'Only when a button is clicked'];

    public function index(): Response
    {
        $this->authorize('marketing.manage');
        $rows = DB::all('SELECT p.*, f.name AS form_name, c.name AS country_name FROM popups p LEFT JOIN forms f ON f.id = p.form_id LEFT JOIN countries c ON c.id = p.country_id WHERE p.deleted_at IS NULL ORDER BY p.is_active DESC, p.name');
        return $this->screen('marketing/popups', ['title' => 'Popups', 'rows' => $rows]);
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

    private function editor(?array $p): Response
    {
        return $this->screen('marketing/popup-edit', [
            'title' => $p ? $p['name'] : 'New popup', 'p' => $p, 'forms' => Options::formsById(),
            'magnets' => array_column(DB::all('SELECT id, title FROM lead_magnets WHERE deleted_at IS NULL'), 'title', 'id'),
            'markets' => array_column(Countries::accessible(), 'name', 'id'),
            'imageUrl' => $p && $p['image_id'] ? (string) DB::value('SELECT url FROM media WHERE id = ?', [$p['image_id']]) : '',
        ]);
    }

    public function store(): Response
    {
        $this->authorize('marketing.manage');
        $id = DB::insert('popups', $this->data());
        Audit::record('created', 'Popup', $id);
        $this->flash('Popup created. Link a button to #popup-' . $id . ' to open it.');
        return $this->redirect('/admin/popups/' . $id);
    }

    public function update(string $id): Response
    {
        $this->authorize('marketing.manage');
        $p = $this->find($this->id($id));
        DB::update('popups', $this->data(), ['id' => $p['id']]);
        Audit::record('updated', 'Popup', $p['id']);
        $this->flash('Popup saved.');
        return $this->redirect('/admin/popups/' . $p['id']);
    }

    /** @return array<string,mixed> */
    private function data(): array
    {
        $d = $this->validate([
            'name' => 'required|max:160', 'type' => 'required|in:' . implode(',', array_keys(self::TYPES)), 'is_active' => 'bool',
            'heading' => 'nullable|max:255', 'body' => 'nullable|max:2000', 'image' => 'nullable|max:1000', 'form_id' => 'nullable|int',
            'lead_magnet_id' => 'nullable|int', 'cta_label' => 'nullable|max:80', 'cta_url' => 'nullable|url|max:500',
            'trigger' => 'required|in:' . implode(',', array_keys(self::TRIGGERS)), 'delay_seconds' => 'nullable|int|between:0,600',
            'scroll_percent' => 'nullable|int|between:0,100', 'device' => 'required|in:ALL,DESKTOP,MOBILE', 'frequency_days' => 'nullable|int|between:0,365',
            'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date', 'url_patterns' => 'nullable|max:4000', 'country_id' => 'nullable|int',
        ]);
        $patterns = array_values(array_filter(array_map(static fn ($l) => '/' . trim(Sanitizer::line($l, 300), '/ '), preg_split('/\R/', (string) $d['url_patterns']) ?: []), static fn ($p) => $p !== '/' || true));
        $patterns = array_values(array_unique(array_filter($patterns, static fn ($p) => $p !== '')));
        if (trim((string) $d['url_patterns']) === '') {
            $patterns = [];
        }
        return [
            'name' => Sanitizer::line((string) $d['name'], 160), 'type' => $d['type'], 'is_active' => $d['is_active'] ? 1 : 0,
            'heading' => $d['heading'] ?: null, 'body' => Sanitizer::text((string) $d['body'], 2000) ?: null,
            'image_id' => $d['image'] ? (DB::value('SELECT id FROM media WHERE url = ?', [$d['image']]) ?: null) : null,
            'form_id' => $d['form_id'] ?: null, 'lead_magnet_id' => $d['lead_magnet_id'] ?: null,
            'cta_label' => $d['cta_label'] ?: null, 'cta_url' => $d['cta_url'] ?: null, 'trigger' => $d['trigger'],
            'delay_seconds' => (int) ($d['delay_seconds'] ?? 5), 'scroll_percent' => (int) ($d['scroll_percent'] ?? 50), 'device' => $d['device'],
            'frequency_days' => (int) ($d['frequency_days'] ?? 7),
            'starts_at' => $d['starts_at'] ? date('Y-m-d H:i:s', strtotime((string) $d['starts_at'])) : null,
            'ends_at' => $d['ends_at'] ? date('Y-m-d H:i:s', strtotime((string) $d['ends_at'])) : null,
            'url_patterns' => $patterns, 'country_id' => $d['country_id'] && in_array((int) $d['country_id'], Countries::accessibleIds(), true) ? $d['country_id'] : null,
        ];
    }

    public function destroy(string $id): Response
    {
        $this->authorize('marketing.manage');
        $p = $this->find($this->id($id));
        DB::update('popups', ['deleted_at' => now(), 'is_active' => 0], ['id' => $p['id']]);
        Audit::record('deleted', 'Popup', $p['id'], $p['name']);
        $this->flash('Popup moved to the recycle bin.');
        return $this->redirect('/admin/popups');
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $p = DB::first('SELECT * FROM popups WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$p) {
            throw HttpException::notFound();
        }
        return $p;
    }
}
