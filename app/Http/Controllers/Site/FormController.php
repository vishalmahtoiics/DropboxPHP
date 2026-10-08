<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\Crypto;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\View;
use App\Http\Controller;
use App\Services\Forms;
use App\Services\LeadCapture;
use App\Services\Settings;

final class FormController extends Controller
{
    public function submit(): Response
    {
        $result = LeadCapture::submit($this->request, $this->request->body);
        unset($result['leadId']);
        return Response::json($result, $result['ok'] ? 200 : 422)->header('Cache-Control', 'no-store');
    }

    /** A form's HTML, for the product CTA dialog and popups. */
    public function render(string $key): Response
    {
        $form = null;
        if ($key === 'default') {
            $form = Forms::bySlug((string) Settings::get('product', 'default_form_slug')) ?? Forms::bySlug('quote') ?? Forms::bySlug('contact');
        } elseif (ctype_digit($key)) {
            $form = Forms::byId((int) $key);
        } else {
            $form = Forms::bySlug($key);
        }
        if (!$form) {
            throw HttpException::notFound();
        }
        $productId = $this->request->int('product') ?: null;
        $productName = $productId ? DB::value("SELECT name FROM products WHERE id = ? AND status = 'PUBLISHED' AND deleted_at IS NULL", [$productId]) : null;
        $html = ($productName ? '<p class="eyebrow">' . e($productName) . '</p>' : '')
            . '<h2>' . e($form['name']) . '</h2>'
            . ($form['description'] ? '<p class="muted">' . e($form['description']) . '</p>' : '')
            . View::partial('site/partials/form', [
                'form' => $form,
                'productId' => $productName ? $productId : null,
                'ctaLabel' => Sanitizer::line($this->request->queryStr('label'), 160),
                'ctaLocation' => Sanitizer::line($this->request->queryStr('location'), 160),
                'compact' => true,
            ]);
        return Response::html($html)->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }

    /**
     * Lead-magnet download: the file is served only with today's token from a
     * completed form, or directly when the magnet has no form.
     */
    public function download(string $slug): Response
    {
        $magnet = DB::first('SELECT * FROM lead_magnets WHERE slug = ? AND is_active = 1 AND deleted_at IS NULL', [$slug]);
        if (!$magnet) {
            throw HttpException::notFound();
        }
        if ($magnet['form_id']) {
            $token = $this->request->queryStr('t');
            $valid = false;
            foreach ([date('Y-m-d'), date('Y-m-d', strtotime('-1 day'))] as $day) {
                if (hash_equals(Crypto::hash($slug . '|' . $day, 'magnet', 24), $token)) {
                    $valid = true;
                }
            }
            if (!$valid) {
                throw HttpException::notFound();
            }
        }
        DB::query('UPDATE lead_magnets SET download_count = download_count + 1 WHERE id = ?', [$magnet['id']]);
        if ($magnet['file_id']) {
            $url = DB::value('SELECT url FROM media WHERE id = ? AND deleted_at IS NULL', [$magnet['file_id']]);
            if ($url) {
                return Response::redirect((string) $url, 302);
            }
        }
        $external = Sanitizer::url((string) $magnet['external_url']);
        if ($external !== '') {
            return Response::redirect($external, 302);
        }
        throw HttpException::notFound();
    }
}
