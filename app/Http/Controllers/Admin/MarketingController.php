<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\ValidationException;
use App\Http\AdminController;
use App\Services\Settings;
use App\Services\Tracking;

/**
 * Vendor tags take an ID only — validated against the vendor's format and
 * dropped into a fixed template. Custom scripts are separate, need
 * marketing.manage, and every change is written to the audit log in full.
 */
final class MarketingController extends AdminController
{
    public function index(): Response
    {
        $this->authorize('marketing.manage');
        return $this->screen('marketing/index', [
            'title' => 'Tracking & tags',
            't' => Settings::group('tracking'),
            'scripts' => DB::all('SELECT * FROM tracking_scripts ORDER BY id'),
            'edit' => $this->request->int('script') ? DB::first('SELECT * FROM tracking_scripts WHERE id = ?', [$this->request->int('script')]) : null,
        ]);
    }

    public function save(): Response
    {
        $this->authorize('marketing.manage');
        $input = $this->request->body;
        foreach (Tracking::ID_PATTERNS as $key => $pattern) {
            $v = strtoupper(trim((string) ($input[$key] ?? '')));
            if ($key === 'tiktok_pixel_id') {
                $v = trim((string) ($input[$key] ?? ''));
            }
            $input[$key] = $v;
            if ($v !== '' && !Tracking::valid($key, $key === 'tiktok_pixel_id' ? strtoupper($v) : $v)) {
                throw ValidationException::with($key, 'That ID does not look right for this vendor.');
            }
        }
        foreach (['ga4_enabled', 'gtm_enabled', 'google_ads_enabled', 'meta_pixel_enabled', 'microsoft_uet_enabled', 'hotjar_enabled', 'linkedin_enabled', 'tiktok_enabled', 'consent_required'] as $k) {
            $input[$k] ??= '';
        }
        $before = Settings::group('tracking');
        Settings::save('tracking', $input, true);
        Audit::record('updated', 'Settings', 'tracking', null, $before, Settings::group('tracking'));
        $this->flash('Tracking saved.');
        return $this->redirect('/admin/marketing');
    }

    public function saveScript(): Response
    {
        $this->authorize('marketing.manage');
        $d = $this->validate(['id' => 'nullable|int', 'name' => 'required|max:120', 'placement' => 'required|in:HEAD,BODY_START,BODY_END',
            'environment' => 'required|in:ALL,PRODUCTION,DEVELOPMENT', 'is_active' => 'bool', 'requires_consent' => 'bool', 'code' => 'required|max:65000']);
        $before = $d['id'] ? DB::first('SELECT * FROM tracking_scripts WHERE id = ?', [$d['id']]) : null;
        $row = ['name' => Sanitizer::line((string) $d['name'], 120), 'placement' => $d['placement'], 'environment' => $d['environment'],
            'is_active' => $d['is_active'] ? 1 : 0, 'requires_consent' => $d['requires_consent'] ? 1 : 0, 'code' => (string) $this->request->body['code']];
        if ($before) {
            DB::update('tracking_scripts', $row, ['id' => $before['id']]);
            $id = (int) $before['id'];
        } else {
            $id = DB::insert('tracking_scripts', $row);
        }
        // The full body is audited: this is the one place arbitrary script reaches the site.
        Audit::record($before ? 'updated' : 'created', 'TrackingScript', $id, $row['name'], $before, $row);
        $this->flash('Script saved' . ($row['is_active'] ? ' and live.' : ' (inactive).'));
        return $this->redirect('/admin/marketing#scripts');
    }

    public function deleteScript(string $id): Response
    {
        $this->authorize('marketing.manage');
        $row = DB::first('SELECT * FROM tracking_scripts WHERE id = ?', [$this->id($id)]);
        if ($row) {
            DB::delete('tracking_scripts', ['id' => $row['id']]);
            Audit::record('deleted', 'TrackingScript', $row['id'], $row['name'], $row);
        }
        $this->flash('Script deleted.');
        return $this->redirect('/admin/marketing#scripts');
    }
}
