<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Response;
use App\Core\ValidationException;
use App\Http\AdminController;
use App\Services\Countries;
use App\Services\SiteImport;

/**
 * Admin → Import site: upload a site package (.zip) made from another site.
 * Pages arrive as drafts; nothing goes live until someone publishes it.
 */
final class SiteImportController extends AdminController
{
    public function show(): Response
    {
        $this->authorizeImport();
        return $this->screen('site-import/index', [
            'title' => 'Import a site',
            'countries' => Countries::accessible(),
            'current' => Countries::adminCurrent(),
            'limit' => self::uploadLimit(),
        ]);
    }

    public function store(): Response
    {
        $this->authorizeImport();
        $upload = $this->request->file('package');
        if (!$upload || (int) $upload['error'] !== UPLOAD_ERR_OK) {
            $tooBig = $upload && in_array((int) $upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            throw ValidationException::with('package', $tooBig
                ? 'The file is larger than the server allows (' . self::uploadLimit() . '). Raise upload_max_filesize and post_max_size in the hosting panel.'
                : 'Choose the site package (.zip) to import.');
        }
        $countryId = $this->request->int('country_id') ?: (int) Countries::adminCurrent()['id'];
        Countries::assertAccess($countryId);
        $report = SiteImport::run((string) $upload['tmp_name'], [
            'country_id' => $countryId,
            'existing' => $this->request->str('existing') === 'skip' ? 'skip' : 'draft',
            'templates' => $this->request->bool('templates'),
            'menus' => $this->request->bool('menus'),
            'redirects' => $this->request->bool('redirects'),
        ], (int) $this->me['id']);
        return $this->screen('site-import/report', ['title' => 'Site imported', 'report' => $report]);
    }

    private function authorizeImport(): void
    {
        $this->authorize('pages.create');
        $this->authorize('media.upload');
    }

    private static function uploadLimit(): string
    {
        $bytes = static function (string $v): int {
            $n = (int) $v;
            return match (strtolower(substr(trim($v), -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
        };
        $min = min($bytes((string) ini_get('upload_max_filesize')), $bytes((string) ini_get('post_max_size')) ?: PHP_INT_MAX);
        return $min >= 1 << 20 ? round($min / 1048576) . ' MB' : round($min / 1024) . ' KB';
    }
}
