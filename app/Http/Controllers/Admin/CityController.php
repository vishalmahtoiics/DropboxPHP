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
use App\Services\Cities;
use App\Services\Countries;
use App\Services\Options;
use App\Services\Slugs;

final class CityController extends AdminController
{
    public function index(): Response
    {
        $this->authorize('pages.view');
        $country = Countries::adminCurrent();
        $rows = DB::all('SELECT c.*, (SELECT COUNT(*) FROM pages p WHERE p.city_id = c.id AND p.deleted_at IS NULL) AS pages,
            (SELECT id FROM pages p WHERE p.city_id = c.id AND p.slug = c.slug AND p.deleted_at IS NULL LIMIT 1) AS landing_id
            FROM cities c WHERE c.country_id = ? ORDER BY c.name', [$country['id']]);
        $edit = $this->request->int('edit') ? DB::first('SELECT * FROM cities WHERE id = ? AND country_id = ?', [$this->request->int('edit'), $country['id']]) : null;
        if ($edit) {
            $edit['settings'] = json_decode((string) $edit['settings'], true) ?: [];
        }
        return $this->screen('cities/index', ['title' => 'Cities', 'rows' => $rows, 'edit' => $edit]);
    }

    public function save(): Response
    {
        $this->authorize('pages.edit');
        $country = Countries::adminCurrent();
        Countries::assertAccess((int) $country['id']);
        $d = $this->validate(['id' => 'nullable|int', 'name' => 'required|max:120', 'slug' => 'nullable|max:80', 'state' => 'nullable|max:120',
            'is_active' => 'bool', 'is_published' => 'bool', 'no_index' => 'bool', 'exclude_from_sitemap' => 'bool',
            'phone' => 'nullable|max:40', 'email' => 'nullable|email', 'address' => 'nullable|max:500']);
        $existing = $d['id'] ? DB::first('SELECT * FROM cities WHERE id = ? AND country_id = ?', [$d['id'], $country['id']]) : null;
        $slug = Slugs::make((string) ($d['slug'] ?: $d['name']));
        if ($slug === '') {
            throw ValidationException::with('slug', 'Enter an address.');
        }
        if (!$existing || $existing['slug'] !== $slug) {
            // The city takes over the first segment: it must not collide with a page or a reserved prefix.
            if (in_array($slug, Countries::RESERVED, true) || in_array($slug, [\App\Services\Settings::get('blog', 'base_path'), \App\Services\Settings::get('product', 'base_path')], true)) {
                throw ValidationException::with('slug', 'That address is reserved by the system.');
            }
            if (DB::value('SELECT id FROM pages WHERE country_id = ? AND (slug = ? OR slug LIKE ?) AND (city_id IS NULL OR city_id <> ?) AND deleted_at IS NULL', [$country['id'], $slug, $slug . '/%', $existing['id'] ?? 0])) {
                throw ValidationException::with('slug', 'A page already uses /' . $slug . '. Choose another address or move that page.');
            }
            if (DB::value('SELECT id FROM cities WHERE country_id = ? AND slug = ? AND id <> ?', [$country['id'], $slug, $existing['id'] ?? 0])) {
                throw ValidationException::with('slug', 'Another city uses this address.');
            }
        }
        if ($existing && $existing['slug'] !== $slug && DB::value('SELECT COUNT(*) FROM pages WHERE city_id = ?', [$existing['id']])) {
            throw ValidationException::with('slug', 'This city already has pages. Its address cannot change; create a redirect instead.');
        }
        $row = ['country_id' => $country['id'], 'name' => Sanitizer::line((string) $d['name'], 120), 'slug' => $slug, 'state' => $d['state'] ?: null,
            'is_active' => $d['is_active'] ? 1 : 0, 'is_published' => $d['is_published'] ? 1 : 0, 'no_index' => $d['no_index'] ? 1 : 0,
            'exclude_from_sitemap' => $d['exclude_from_sitemap'] ? 1 : 0,
            'settings' => ['phone' => $d['phone'] ?: '', 'email' => $d['email'] ?: '', 'address' => Sanitizer::text((string) $d['address'], 500)]];
        if ($existing) {
            DB::update('cities', $row, ['id' => $existing['id']]);
            $id = (int) $existing['id'];
        } else {
            $id = DB::insert('cities', $row);
        }
        Audit::record($existing ? 'updated' : 'created', 'City', $id, $row['name']);
        $this->flash('City saved.');
        return $this->redirect('/admin/cities');
    }

    public function destroy(string $id): Response
    {
        $this->authorize('pages.delete');
        $city = DB::first('SELECT * FROM cities WHERE id = ?', [$this->id($id)]);
        if (!$city) {
            throw HttpException::notFound();
        }
        Countries::assertAccess((int) $city['country_id']);
        if (DB::value('SELECT COUNT(*) FROM pages WHERE city_id = ?', [$city['id']])) {
            $this->flash('Delete or move this city\'s pages first (deactivating the city takes them offline).', 'error');
            return $this->redirect('/admin/cities');
        }
        DB::delete('cities', ['id' => $city['id']]);
        Audit::record('deleted', 'City', $city['id'], $city['name']);
        $this->flash('City deleted.');
        return $this->redirect('/admin/cities');
    }

    public function generator(): Response
    {
        $this->authorize('pages.create');
        $country = Countries::adminCurrent();
        return $this->screen('cities/generator', [
            'title' => 'City Page Generator',
            'pages' => Options::pages((int) $country['id']),
            'cities' => DB::all('SELECT * FROM cities WHERE country_id = ? AND is_active = 1 ORDER BY name', [$country['id']]),
            'batches' => DB::all('SELECT generation_batch, MIN(generated_at) AS at, COUNT(*) AS n, MIN(generated_from_id) AS tpl FROM pages WHERE country_id = ? AND generation_batch IS NOT NULL AND deleted_at IS NULL GROUP BY generation_batch ORDER BY at DESC LIMIT 20', [$country['id']]),
        ]);
    }

    public function generate(): Response
    {
        $this->authorize('pages.create');
        $country = Countries::adminCurrent();
        $d = $this->validate(['template_id' => 'required|int', 'slug_pattern' => 'required|max:200', 'title_pattern' => 'nullable|max:255', 'cities' => 'array', 'publish' => 'bool']);
        $template = DB::first('SELECT country_id FROM pages WHERE id = ?', [$d['template_id']]);
        if (!$template || (int) $template['country_id'] !== (int) $country['id']) {
            throw ValidationException::with('template_id', 'Choose a page from this market.');
        }
        if ($d['publish']) {
            $this->authorize('pages.publish');
        }
        if (!$d['cities']) {
            throw ValidationException::with('cities', 'Choose at least one city.');
        }
        $result = Cities::generate((int) $d['template_id'], array_map('intval', $d['cities']), (string) $d['slug_pattern'], (string) $d['title_pattern'], (bool) $d['publish'], (int) $this->me['id']);
        Audit::record('generated', 'Page', null, count($result['created']) . ' city pages from template #' . $d['template_id']);
        $msg = count($result['created']) . ' page(s) created' . ($d['publish'] ? ' and published' : ' as drafts') . '.';
        if ($result['skipped']) {
            $msg .= ' Skipped: ' . implode('; ', array_slice($result['skipped'], 0, 8));
        }
        $this->flash($msg, $result['skipped'] ? 'warning' : 'success');
        return $this->redirect('/admin/cities/generator');
    }
}
