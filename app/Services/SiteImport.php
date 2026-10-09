<?php

declare(strict_types=1);

namespace App\Services;

use App\Builder\Layout;
use App\Builder\Store;
use App\Builder\Transfer;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Sanitizer;
use App\Core\ValidationException;
use Throwable;
use ZipArchive;

/**
 * Imports a whole site from a package (.zip): pages built in the builder,
 * their images, forms, header/footer templates, menus and redirects.
 *
 * The package:
 *   manifest.json   {"format":"site-package","version":1,"name":"example.com",
 *                    "pages":[{"file":"pages/home.json","title":"Home","slug":"","seo_title":"…","seo_description":"…"}],
 *                    "templates":[{"file":"templates/header.json","kind":"header","name":"Header"}],
 *                    "forms":[{"slug":"contact","name":"Contact","submit_label":"Send","success_message":"…",
 *                              "fields":[{"type":"NAME","label":"Name","name":"name","placeholder":"Name","required":true}]}],
 *                    "menus":{"HEADER":[{"label":"Home","url":"/","children":[]}]},
 *                    "redirects":[{"from":"/old","to":"/new"}]}
 *   pages/*.json, templates/*.json   builder layout files (App\Builder\Transfer)
 *   media/…                          images; layouts refer to them as "pkg:media/…"
 *   (manifest) "media_sources":      optional {"media/2018/11/a.jpg": ["/wp-content/uploads/2018/11/a.jpg", …]}:
 *                                    the image's addresses on the old site, redirected to its new one
 *
 * Images under a year/month folder keep that folder and their name (media/2018/11/A.jpg →
 * /media/2018/11/a.jpg) when the address is free, so a site's image addresses are predictable;
 * importing the same file again reuses it instead of storing a copy.
 *
 * Everything is untrusted: the zip is read entry by entry with size limits
 * (nothing is extracted to disk under its own name), images go through the
 * normal upload checks and layouts through Transfer::parse.
 *
 * By default nothing goes live: new pages are drafts, pages that already
 * exist keep their live version (the import goes into their builder draft or
 * is skipped) and the header and footer arrive as inactive templates.
 * With "replace" the package becomes the live site: existing pages, forms,
 * templates of the same name and menus are replaced, every imported page is
 * published, and the package's header and footer are made active.
 */
final class SiteImport
{
    public const FORMAT = 'site-package';
    public const VERSION = 1;
    public const MAX_ENTRIES = 5000;
    public const MAX_TOTAL_BYTES = 500 * 1024 * 1024;   // uncompressed
    public const MAX_MANIFEST_BYTES = 2 * 1024 * 1024;
    public const MAX_PAGES = 1000;
    private const MENU_LOCATIONS = ['HEADER', 'FOOTER', 'FOOTER_SECONDARY', 'LEGAL', 'MOBILE'];

    private ZipArchive $zip;
    /** @var array<string, string> package media path => site URL */
    private array $media = [];
    /** @var array{pages: list<array>, templates: list<array>, forms: list<array>, menus: list<string>, redirects: int, media: int, warnings: list<string>} */
    private array $report = ['pages' => [], 'templates' => [], 'forms' => [], 'menus' => [], 'redirects' => 0, 'media' => 0, 'images' => [], 'warnings' => []];

    /**
     * @param array{country_id:int, existing:string, templates:bool, menus:bool, redirects:bool, replace?:bool} $options
     *        existing: "draft" puts the import into an existing page's builder draft, "skip" leaves it alone;
     *        replace: overwrite what exists and publish (existing, templates and menus are then ignored)
     * @return array the report
     */
    public static function run(string $zipPath, array $options, int $userId): array
    {
        if (!empty($options['replace'])) {
            $options = ['replace' => true, 'existing' => 'replace', 'templates' => true, 'menus' => true] + $options;
        }
        $options['replace'] = !empty($options['replace']);
        $self = new self();
        return $self->import($zipPath, $options, $userId);
    }

    private function import(string $zipPath, array $options, int $userId): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw ValidationException::with('file', 'The PHP zip extension is needed to import a site package.');
        }
        $this->zip = new ZipArchive();
        if ($this->zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw ValidationException::with('file', 'This file is not a zip archive.');
        }
        try {
            $this->checkArchive();
            $manifest = $this->manifest();
            @set_time_limit(600);

            $this->importMedia($manifest, $userId);
            DB::transaction(function () use ($manifest, $options, $userId): void {
                $this->importForms($manifest['forms'] ?? [], $options['replace']);
                if ($options['templates']) {
                    $this->importTemplates($manifest['templates'] ?? [], $userId, $options['replace']);
                }
                $this->importPages($manifest['pages'] ?? [], $options, $userId);
                if ($options['menus']) {
                    $this->importMenus($manifest['menus'] ?? [], (int) $options['country_id']);
                }
            });
            if ($options['redirects']) {
                $this->importRedirects(array_merge((array) ($manifest['redirects'] ?? []), $this->mediaRedirects((array) ($manifest['media_sources'] ?? []))), $options['replace']);
            }
        } finally {
            $this->zip->close();
        }
        $created = count(array_filter($this->report['pages'], static fn ($p) => in_array($p['action'], ['created', 'published'], true)));
        Audit::record('created', 'Site import', null, sprintf('Imported "%s"%s: %d new pages, %d images', (string) ($manifest['name'] ?? 'package'), $options['replace'] ? ' (replacing the site)' : '', $created, $this->report['media']));
        return $this->report;
    }

    // --- archive -----------------------------------------------------------------------

    private function checkArchive(): void
    {
        $count = $this->zip->numFiles;
        if ($count > self::MAX_ENTRIES) {
            throw ValidationException::with('file', 'The package has too many files (at most ' . self::MAX_ENTRIES . ').');
        }
        $total = 0;
        for ($i = 0; $i < $count; $i++) {
            $stat = $this->zip->statIndex($i);
            $total += (int) ($stat['size'] ?? 0);
            if ($total > self::MAX_TOTAL_BYTES) {
                throw ValidationException::with('file', 'The package is too large once unpacked (at most 500 MB).');
            }
        }
    }

    private function manifest(): array
    {
        $json = $this->entry('manifest.json', self::MAX_MANIFEST_BYTES);
        $data = $json !== null ? json_decode($json, true) : null;
        if (!is_array($data) || ($data['format'] ?? null) !== self::FORMAT) {
            throw ValidationException::with('file', 'This is not a site package (manifest.json is missing or not valid).');
        }
        if ((int) ($data['version'] ?? 0) > self::VERSION) {
            throw ValidationException::with('file', 'This package was made by a newer version of the site. Update the site first.');
        }
        if (count((array) ($data['pages'] ?? [])) > self::MAX_PAGES) {
            throw ValidationException::with('file', 'The package has too many pages (at most ' . self::MAX_PAGES . ').');
        }
        return $data;
    }

    /** One file of the package as a string, or null when it is missing or larger than $max. */
    private function entry(string $name, int $max): ?string
    {
        if (!self::safePath($name)) {
            return null;
        }
        $stat = $this->zip->statName($name);
        if ($stat === false || (int) $stat['size'] > $max) {
            return null;
        }
        $data = $this->zip->getFromName($name, $max + 1);
        return is_string($data) && strlen($data) <= $max ? $data : null;
    }

    private static function safePath(string $path): bool
    {
        return $path !== '' && !str_starts_with($path, '/') && !str_contains($path, '\\')
            && !in_array('..', explode('/', $path), true) && preg_match('#^[A-Za-z0-9_\-./]+$#', $path) === 1;
    }

    // --- media -------------------------------------------------------------------------

    private function importMedia(array $manifest, int $userId): void
    {
        $paths = [];
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $name = (string) $this->zip->getNameIndex($i);
            if (str_starts_with($name, 'media/') && !str_ends_with($name, '/') && self::safePath($name)) {
                $paths[] = $name;
            }
        }
        if (!$paths) {
            return;
        }
        $folder = null;
        $max = (int) config('app.max_upload_bytes');
        foreach ($paths as $path) {
            $data = $this->entry($path, $max);
            if ($data === null) {
                $this->report['warnings'][] = 'Skipped ' . $path . ': larger than the upload limit.';
                continue;
            }
            $key = Storage::keepKey($path);
            $same = $key !== null ? $this->sameFile($key, $data) : null;
            if ($same !== null) {
                // Imported before: keep using it.
                $this->media[$path] = $same;
                $this->report['images'][] = ['path' => $path, 'url' => $same, 'reused' => true];
                continue;
            }
            $folder ??= DB::insert('media_folders', ['name' => mb_substr('Imported: ' . Sanitizer::line((string) ($manifest['name'] ?? 'site'), 100), 0, 120)]);
            $tmp = tempnam(sys_get_temp_dir(), 'imp');
            try {
                file_put_contents($tmp, $data);
                $row = Uploads::store(['name' => basename($path), 'tmp_name' => $tmp, 'size' => strlen($data), 'error' => UPLOAD_ERR_OK], $userId, $folder, $key);
                $this->media[$path] = (string) $row['url'];
                $this->report['media']++;
                $this->report['images'][] = ['path' => $path, 'url' => (string) $row['url'], 'reused' => false];
            } catch (ValidationException $e) {
                $this->report['warnings'][] = 'Skipped ' . $path . ': ' . $e->getMessage();
            } finally {
                @unlink($tmp);
            }
        }
    }

    /** The address of a stored file at $key with exactly these bytes, or null. */
    private function sameFile(string $key, string $data): ?string
    {
        $row = DB::first('SELECT url, provider, size FROM media WHERE storage_key = ?', [$key]);
        if (!$row || (int) $row['size'] !== strlen($data)) {
            return null;
        }
        if ($row['provider'] === 'local') {
            $file = Storage::localPath($key);
            if ($file === null || hash_file('sha256', $file) !== hash('sha256', $data)) {
                return null;
            }
        }
        return (string) $row['url'];
    }

    /** Redirects from an image's old-site addresses to its new one. @return list<array{from:string,to:string}> */
    private function mediaRedirects(array $sources): array
    {
        $out = [];
        foreach (array_slice($sources, 0, 5000, true) as $path => $olds) {
            $to = $this->media[(string) $path] ?? null;
            if ($to === null) {
                continue;
            }
            foreach (array_slice((array) $olds, 0, 20) as $old) {
                if (is_string($old) && str_starts_with($old, '/') && Redirects::normalise($old) !== Redirects::normalise($to)) {
                    $out[] = ['from' => $old, 'to' => $to];
                }
            }
        }
        return $out;
    }

    /** Replace "pkg:media/…" with the uploaded file's address, everywhere in a decoded layout. */
    private function withMedia(mixed $value): mixed
    {
        if (is_string($value)) {
            if (!str_contains($value, 'pkg:')) {
                return $value;
            }
            return (string) preg_replace_callback('#pkg:(media/[A-Za-z0-9_\-./]+)#', fn ($m) => $this->media[$m[1]] ?? '', $value);
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->withMedia($v);
            }
        }
        return $value;
    }

    /** A layout file from the package, with media addresses filled in and cleaned. */
    private function layout(string $file): ?array
    {
        $json = $this->entry($file, Layout::MAX_BYTES);
        $data = $json !== null ? json_decode($json, true) : null;
        if (!is_array($data)) {
            $this->report['warnings'][] = 'Skipped ' . $file . ': missing or not valid JSON.';
            return null;
        }
        try {
            $in = Transfer::parse((string) json_encode($this->withMedia($data)), Auth::can('builder.code'));
        } catch (ValidationException $e) {
            $this->report['warnings'][] = 'Skipped ' . $file . ': ' . $e->getMessage();
            return null;
        }
        if ($in['codeRemoved']) {
            $this->report['warnings'][] = $file . ': custom HTML/CSS left out (your role cannot add code).';
        }
        return $in['layout'];
    }

    // --- forms -------------------------------------------------------------------------

    private function importForms(array $forms, bool $replace): void
    {
        foreach (array_slice($forms, 0, 200) as $form) {
            if (!is_array($form)) {
                continue;
            }
            $name = Sanitizer::line((string) ($form['name'] ?? ''), 160);
            $slug = Slugs::make((string) ($form['slug'] ?? $name));
            if ($slug === '' || $name === '') {
                continue;
            }
            $existingId = (int) DB::value('SELECT id FROM forms WHERE slug = ?', [$slug]);
            if ($existingId && !$replace) {
                $this->report['forms'][] = ['slug' => $slug, 'name' => $name, 'action' => 'kept'];
                continue;
            }
            if ($existingId) {
                if (!Auth::can('forms.edit')) {
                    $this->report['warnings'][] = 'Form "' . $name . '" was kept: your role cannot edit forms.';
                    continue;
                }
                $fields = $this->formFields((array) ($form['fields'] ?? []));
                if (!$fields) {
                    continue;
                }
                // Leads keep their own copy of what was sent, so the fields can be rebuilt.
                DB::update('forms', $this->formRow($form, $name), ['id' => $existingId]);
                DB::delete('form_fields', ['form_id' => $existingId]);
                foreach ($fields as $i => $f) {
                    DB::insert('form_fields', $f + ['form_id' => $existingId, 'sort_order' => ($i + 1) * 10]);
                }
                $this->report['forms'][] = ['slug' => $slug, 'name' => $name, 'action' => 'replaced'];
                continue;
            }
            if (!Auth::can('forms.create')) {
                $this->report['warnings'][] = 'Form "' . $name . '" was not created: your role cannot create forms.';
                continue;
            }
            $fields = $this->formFields((array) ($form['fields'] ?? []));
            if (!$fields) {
                continue;
            }
            $id = DB::insert('forms', $this->formRow($form, $name) + [
                'slug' => $slug,
                'is_active' => 1,
                'lead_source' => 'Website Form',
                'creates_lead' => 1,
            ]);
            foreach ($fields as $i => $f) {
                DB::insert('form_fields', $f + ['form_id' => $id, 'sort_order' => ($i + 1) * 10]);
            }
            $this->report['forms'][] = ['slug' => $slug, 'name' => $name, 'action' => 'created'];
        }
        Forms::clearCache();
    }

    /** The columns of a form row that come from the package. */
    private function formRow(array $form, string $name): array
    {
        return [
            'name' => $name,
            'submit_label' => Sanitizer::line((string) ($form['submit_label'] ?? 'Submit'), 80) ?: 'Submit',
            'success_message' => Sanitizer::text((string) ($form['success_message'] ?? ''), 1000) ?: 'Thank you! We will get back to you shortly.',
            'design' => ['columns' => (int) ($form['columns'] ?? 1) === 2 ? 2 : 1],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function formFields(array $fields): array
    {
        $out = [];
        $names = [];
        foreach (array_slice($fields, 0, 60) as $i => $f) {
            $type = is_array($f) ? strtoupper((string) ($f['type'] ?? '')) : '';
            $label = is_array($f) ? Sanitizer::line((string) ($f['label'] ?? ''), 200) : '';
            if (!isset(Forms::FIELD_TYPES[$type]) || $type === 'CONSENT' || $label === '') {
                continue;
            }
            $name = trim((string) preg_replace('/[^a-z0-9_]+/', '_', strtolower((string) ($f['name'] ?? $label))), '_');
            $name = substr($name, 0, 60) ?: 'field_' . $i;
            if (!preg_match('/^[a-z]/', $name)) {
                $name = 'f_' . $name;
            }
            if (isset($names[$name])) {
                $name = substr($name, 0, 55) . '_' . $i;
            }
            $names[$name] = true;
            $options = array_values(array_filter(array_map(static fn ($o) => Sanitizer::line(is_scalar($o) ? (string) $o : '', 200), array_slice((array) ($f['options'] ?? []), 0, 100))));
            if (in_array($type, ['SELECT', 'RADIO', 'MULTISELECT'], true) && !$options) {
                continue;
            }
            $out[] = [
                'type' => $type,
                'label' => $label,
                'name' => $name,
                'placeholder' => Sanitizer::line((string) ($f['placeholder'] ?? ''), 200) ?: null,
                'options' => $options ?: null,
                'is_required' => !empty($f['required']) ? 1 : 0,
                'is_enabled' => 1,
                'show_label' => !empty($f['show_label']) ? 1 : 0,
                'width' => in_array($f['width'] ?? '', ['full', 'half', 'third'], true) ? $f['width'] : 'full',
            ];
        }
        return $out;
    }

    // --- templates ---------------------------------------------------------------------

    private function importTemplates(array $templates, int $userId, bool $replace): void
    {
        foreach (array_slice($templates, 0, 100) as $t) {
            $kind = is_array($t) ? (string) ($t['kind'] ?? '') : '';
            if (!in_array($kind, Transfer::KINDS, true)) {
                continue;
            }
            $need = match ($kind) { 'product' => 'products.edit', 'header', 'footer' => 'navigation.manage', default => 'pages.edit' };
            $name = Sanitizer::line((string) ($t['name'] ?? ucfirst($kind)), 160) ?: ucfirst($kind);
            if (!Auth::can($need)) {
                $this->report['warnings'][] = 'Template "' . $name . '" was not imported: your role cannot manage ' . $kind . ' templates.';
                continue;
            }
            $layout = $this->layout((string) ($t['file'] ?? ''));
            if ($layout === null) {
                continue;
            }
            $existing = $replace ? DB::first('SELECT * FROM builder_templates WHERE kind = ? AND name = ? ORDER BY id LIMIT 1', [$kind, $name]) : null;
            if ($existing) {
                Store::save('template', $existing, $layout, true);
                $id = (int) $existing['id'];
            } else {
                $id = DB::insert('builder_templates', ['name' => $name, 'kind' => $kind, 'layout' => Layout::encode($layout), 'created_by_id' => $userId, 'updated_by_id' => $userId]);
            }
            // Without "replace" never made active: the site's header and footer only change when someone chooses to.
            $active = $replace && in_array($kind, ['header', 'footer'], true);
            if ($active) {
                DB::query('UPDATE builder_templates SET is_default = 0 WHERE kind = ?', [$kind]);
                DB::update('builder_templates', ['is_default' => 1], ['id' => $id]);
            }
            $this->report['templates'][] = ['id' => $id, 'name' => $name, 'kind' => $kind, 'action' => $existing ? 'replaced' : 'created', 'active' => $active];
        }
    }

    // --- pages -------------------------------------------------------------------------

    private function importPages(array $pages, array $options, int $userId): void
    {
        $countryId = (int) $options['country_id'];
        foreach ($pages as $p) {
            if (!is_array($p)) {
                continue;
            }
            $title = Sanitizer::line((string) ($p['title'] ?? ''), 255);
            $slug = Slugs::make((string) ($p['slug'] ?? ''), true);
            if ($title === '') {
                continue;
            }
            $replace = $options['replace'];
            $existing = DB::first('SELECT * FROM pages WHERE country_id = ? AND slug = ? AND deleted_at IS NULL', [$countryId, $slug]);
            if ($existing && !$replace && $options['existing'] !== 'draft') {
                $this->report['pages'][] = ['id' => (int) $existing['id'], 'title' => $title, 'slug' => $slug, 'action' => 'skipped', 'note' => 'a page already uses this address'];
                continue;
            }
            if (!$existing) {
                try {
                    Slugs::assertPageSlug($slug, $countryId);
                } catch (ValidationException $e) {
                    $this->report['pages'][] = ['id' => null, 'title' => $title, 'slug' => $slug, 'action' => 'skipped', 'note' => $e->getMessage()];
                    continue;
                }
            }
            $layout = $this->layout((string) ($p['file'] ?? ''));
            if ($layout === null) {
                $this->report['pages'][] = ['id' => $existing['id'] ?? null, 'title' => $title, 'slug' => $slug, 'action' => 'skipped', 'note' => 'its layout could not be read'];
                continue;
            }
            $seo = [
                'seo_title' => Sanitizer::line((string) ($p['seo_title'] ?? ''), 255) ?: null,
                'seo_description' => Sanitizer::text((string) ($p['seo_description'] ?? ''), 500) ?: null,
            ];
            if ($existing && $replace) {
                // The package's version goes live (the old one stays in the page's builder history).
                Store::save('page', $existing, $layout, true);
                DB::update('pages', $seo + [
                    'title' => $title,
                    'status' => 'PUBLISHED',
                    'published_at' => $existing['published_at'] && strtotime((string) $existing['published_at']) <= time() ? $existing['published_at'] : now(),
                    'updated_by_id' => $userId,
                ], ['id' => $existing['id']]);
                $this->report['pages'][] = ['id' => (int) $existing['id'], 'title' => $title, 'slug' => $slug, 'action' => 'replaced', 'note' => 'replaced and published'];
                continue;
            }
            if ($existing) {
                // The live page stays as it is; the import waits in the builder as a draft.
                DB::update('pages', ['builder_draft' => Layout::encode($layout), 'updated_by_id' => $userId], ['id' => $existing['id']]);
                $this->report['pages'][] = ['id' => (int) $existing['id'], 'title' => $title, 'slug' => $slug, 'action' => 'draft', 'note' => 'existing page: the import is its builder draft'];
                continue;
            }
            $id = DB::insert('pages', $seo + [
                'country_id' => $countryId,
                'title' => $title,
                'slug' => $slug,
                'status' => $replace ? 'PUBLISHED' : 'DRAFT',
                'published_at' => $replace ? now() : null,
                'show_header' => array_key_exists('show_header', $p) && !$p['show_header'] ? 0 : 1,
                'show_footer' => array_key_exists('show_footer', $p) && !$p['show_footer'] ? 0 : 1,
                'editor' => 'builder',
                'builder_layout' => Layout::encode($layout),
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);
            $this->report['pages'][] = ['id' => $id, 'title' => $title, 'slug' => $slug, 'action' => $replace ? 'published' : 'created', 'note' => ''];
        }
    }

    // --- menus and redirects -----------------------------------------------------------

    private function importMenus(array $menus, int $countryId): void
    {
        if (!Auth::can('navigation.manage')) {
            $this->report['warnings'][] = 'Menus were not imported: your role cannot manage navigation.';
            return;
        }
        foreach ($menus as $location => $items) {
            if (!in_array($location, self::MENU_LOCATIONS, true) || !is_array($items)) {
                continue;
            }
            $navId = DB::value('SELECT id FROM navigations WHERE country_id = ? AND location = ?', [$countryId, $location]);
            if (!$navId) {
                $navId = DB::insert('navigations', ['country_id' => $countryId, 'name' => ucfirst(strtolower(str_replace('_', ' ', $location))), 'location' => $location]);
            }
            DB::delete('navigation_items', ['navigation_id' => $navId]);
            $this->menuItems((int) $navId, null, $items, 1);
            $this->report['menus'][] = $location;
        }
    }

    private function menuItems(int $navId, ?int $parent, array $items, int $depth): void
    {
        foreach (array_slice($items, 0, 60) as $i => $item) {
            $label = is_array($item) ? Sanitizer::line((string) ($item['label'] ?? ''), 120) : '';
            if ($label === '') {
                continue;
            }
            $url = trim((string) ($item['url'] ?? ''));
            $external = (bool) preg_match('#^https?://#i', $url);
            $url = $external ? Sanitizer::url($url) : ($url !== '' && str_starts_with($url, '/') ? Sanitizer::url($url) : '');
            $id = DB::insert('navigation_items', [
                'navigation_id' => $navId, 'parent_id' => $parent, 'label' => $label,
                'link_type' => $external ? 'EXTERNAL' : 'INTERNAL', 'url' => $url !== '' ? $url : null,
                'open_in_new_tab' => !empty($item['new_tab']) ? 1 : 0, 'sort_order' => $i + 1,
            ]);
            if ($depth < 3 && !empty($item['children']) && is_array($item['children'])) {
                $this->menuItems($navId, $id, $item['children'], $depth + 1);
            }
        }
    }

    private function importRedirects(array $redirects, bool $replace): void
    {
        if (!Auth::can('seo.manage')) {
            $this->report['warnings'][] = 'Redirects were not added: your role cannot manage redirects.';
            return;
        }
        foreach (array_slice($redirects, 0, 10000) as $r) {
            if (!is_array($r) || !is_string($r['from'] ?? null) || !is_string($r['to'] ?? null) || !str_starts_with($r['from'], '/')) {
                continue;
            }
            try {
                $existing = $replace ? DB::value('SELECT id FROM redirects WHERE source_hash = ?', [hash('sha256', Redirects::normalise($r['from']))]) : null;
                Redirects::save(['source' => $r['from'], 'destination' => $r['to'], 'is_active' => 1, 'note' => 'Site import'], $existing ? (int) $existing : null);
                $this->report['redirects']++;
            } catch (ValidationException) {
                // already redirected, or it would point to itself
            } catch (Throwable $e) {
                $this->report['warnings'][] = 'Redirect ' . $r['from'] . ' was not added.';
            }
        }
    }
}
