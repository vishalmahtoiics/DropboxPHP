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

/**
 * Markets. A country owns a URL prefix, currency, locale, company details
 * and SEO defaults. Adding one is a row here plus its content — no code.
 */
final class CountryController extends AdminController
{
    public const SETTINGS = [
        'company_name' => 'Company name', 'legal_name' => 'Legal name', 'sales_phone' => 'Sales phone', 'support_phone' => 'Support phone',
        'whatsapp' => 'WhatsApp', 'sales_email' => 'Sales email', 'support_email' => 'Support email', 'address' => 'Address',
        'business_hours' => 'Business hours', 'tax_label' => 'Tax label (GSTIN, TRN…)', 'tax_number' => 'Tax number',
        'header_cta_label' => 'Header button label', 'header_cta_url' => 'Header button link',
        'default_title' => 'Default SEO title', 'title_template' => 'Title template (%s = page title)', 'default_description' => 'Default meta description',
        'organization_type' => 'Schema type (Organization, LocalBusiness…)', 'robots_disallow' => 'Extra robots.txt disallow rules (one path per line)',
    ];

    public function index(): Response
    {
        $this->authorize('countries.manage');
        $rows = DB::all('SELECT c.*, (SELECT COUNT(*) FROM pages p WHERE p.country_id = c.id AND p.deleted_at IS NULL) AS pages,
            (SELECT COUNT(*) FROM leads l WHERE l.country_id = c.id AND l.deleted_at IS NULL) AS leads FROM countries c ORDER BY c.is_default DESC, c.sort_order, c.name');
        $edit = $this->request->int('edit') ? DB::first('SELECT * FROM countries WHERE id = ?', [$this->request->int('edit')]) : null;
        if ($edit) {
            $edit['settings'] = json_decode((string) $edit['settings'], true) ?: [];
        }
        return $this->screen('settings/countries', ['title' => 'Countries', 'rows' => $rows, 'edit' => $edit, 'fields' => self::SETTINGS, 'new' => $this->request->queryStr('new') === '1']);
    }

    public function save(): Response
    {
        $this->authorize('countries.manage');
        $d = $this->validate([
            'id' => 'nullable|int', 'name' => 'required|max:120', 'code' => 'required|regex:/^[A-Za-z]{2}$/', 'slug' => 'nullable|max:16',
            'locale' => 'required|max:16', 'currency' => 'required|regex:/^[A-Za-z]{3}$/', 'currency_symbol' => 'required|max:8',
            'phone_code' => 'nullable|max:8', 'timezone' => 'required|max:64', 'is_active' => 'bool', 'is_published' => 'bool',
            'is_default' => 'bool', 'sort_order' => 'nullable|int', 'noindex' => 'bool', 'exclude_from_sitemap' => 'bool',
            'copy_from' => 'nullable|int',
        ]);
        if (!in_array($d['timezone'], timezone_identifiers_list(), true)) {
            throw ValidationException::with('timezone', 'Choose a valid timezone, e.g. Asia/Dubai.');
        }
        $id = $d['id'] ?: null;
        $existing = $id ? DB::first('SELECT * FROM countries WHERE id = ?', [$id]) : null;
        $isDefault = $d['is_default'] || ($existing && (int) $existing['is_default'] === 1);
        $slug = $isDefault ? '' : strtolower((string) $d['slug']);
        if (!$isDefault) {
            if (!preg_match('/^[a-z]{2,10}(-[a-z]{2,5})?$/', $slug)) {
                throw ValidationException::with('slug', 'Use a short lowercase prefix, e.g. "ae" or "en-gb".');
            }
            if (in_array($slug, Countries::RESERVED, true) || in_array($slug, [\App\Services\Settings::get('blog', 'base_path'), \App\Services\Settings::get('product', 'base_path')], true)) {
                throw ValidationException::with('slug', 'That prefix is reserved by the system.');
            }
            if (DB::value("SELECT id FROM pages WHERE slug = ? OR slug LIKE ? LIMIT 1", [$slug, $slug . '/%'])) {
                throw ValidationException::with('slug', 'A page already uses /' . $slug . '. Change that page first.');
            }
        }
        $code = strtoupper((string) $d['code']);
        if (DB::value('SELECT id FROM countries WHERE (code = ? OR (slug = ? AND slug <> \'\')) AND id <> ?', [$code, $slug, $id ?? 0])) {
            throw ValidationException::with('code', 'Another market uses this code or prefix.');
        }
        $settings = $existing ? (json_decode((string) $existing['settings'], true) ?: []) : [];
        foreach (self::SETTINGS as $k => $label) {
            $v = $this->request->str('s_' . $k);
            $settings[$k] = in_array($k, ['address', 'default_description', 'robots_disallow'], true) ? Sanitizer::text($v, 2000) : Sanitizer::line($v, 255);
        }
        $settings['noindex'] = $d['noindex'];
        $settings['exclude_from_sitemap'] = $d['exclude_from_sitemap'];
        $row = [
            'name' => Sanitizer::line((string) $d['name'], 120), 'code' => $code, 'slug' => $slug, 'locale' => $d['locale'],
            'currency' => strtoupper((string) $d['currency']), 'currency_symbol' => $d['currency_symbol'], 'phone_code' => $d['phone_code'] ?: null,
            'timezone' => $d['timezone'], 'is_active' => $isDefault ? 1 : ($d['is_active'] ? 1 : 0), 'is_published' => $d['is_published'] ? 1 : 0,
            'sort_order' => (int) $d['sort_order'], 'settings' => $settings,
        ];
        DB::transaction(function () use (&$id, $row, $d, $existing): void {
            if ($d['is_default'] && (!$existing || (int) $existing['is_default'] === 0)) {
                // Moving the default market changes every root address: only allowed with the old default's prefix freed.
                DB::query("UPDATE countries SET is_default = 0, slug = LOWER(code) WHERE is_default = 1");
                $row['is_default'] = 1;
                $row['slug'] = '';
            }
            if ($id) {
                DB::update('countries', $row, ['id' => $id]);
            } else {
                $id = DB::insert('countries', $row);
                DB::insert('user_countries', ['user_id' => $this->me['id'], 'country_id' => $id]);
                if ($d['copy_from'] && in_array((int) $d['copy_from'], Countries::accessibleIds(), true)) {
                    \App\Services\MarketCopy::copy((int) $d['copy_from'], (int) $id, (int) $this->me['id']);
                }
            }
        });
        Countries::reset();
        Audit::record($existing ? 'updated' : 'created', 'Country', $id, $row['name']);
        $this->flash($existing ? 'Market saved.' : 'Market created. Its pages are drafts until you publish them.');
        return $this->redirect('/admin/countries?edit=' . $id);
    }

    public function destroy(string $id): Response
    {
        $this->authorize('countries.manage');
        $c = DB::first('SELECT * FROM countries WHERE id = ?', [$this->id($id)]);
        if (!$c) {
            throw HttpException::notFound();
        }
        if ((int) $c['is_default'] === 1) {
            $this->flash('The default market cannot be deleted.', 'error');
            return $this->redirect('/admin/countries');
        }
        $refs = (int) DB::value('SELECT (SELECT COUNT(*) FROM pages WHERE country_id = ?) + (SELECT COUNT(*) FROM leads WHERE country_id = ?) + (SELECT COUNT(*) FROM blog_posts WHERE country_id = ?)', [$c['id'], $c['id'], $c['id']]);
        if ($refs > 0) {
            $this->flash("This market still has $refs pages, articles or leads. Deactivate it instead, or remove its content first.", 'error');
            return $this->redirect('/admin/countries?edit=' . $c['id']);
        }
        DB::delete('countries', ['id' => $c['id']]);
        Countries::reset();
        Audit::record('deleted', 'Country', $c['id'], $c['name']);
        $this->flash('Market deleted.');
        return $this->redirect('/admin/countries');
    }
}
