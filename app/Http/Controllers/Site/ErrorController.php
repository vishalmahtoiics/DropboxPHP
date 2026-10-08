<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\Auth;
use App\Core\ErrorHandler;
use App\Core\Response;
use App\Core\View;
use App\Http\Controller;
use App\Services\SiteContext;
use Throwable;

/**
 * Error pages. A 404 is the site's own "not found" page — the same one an
 * anonymous visitor gets for /admin, so probing reveals nothing.
 */
final class ErrorController extends Controller
{
    public function show(int $status, string $surface = 'site'): Response
    {
        try {
            if ($status === 403 && Auth::user()) {
                View::share('me', Auth::user());
                View::share('adminCountry', \App\Services\Countries::adminCurrent());
                return Response::html(View::render('admin/forbidden', ['title' => 'Not allowed'], 'layouts/admin'), 403);
            }
            if ($status === 404 || $status === 403) {
                SiteContext::boot($this->request);
                $html = View::render('site/error', [
                    'title' => 'Page not found',
                    'heading' => 'We could not find that page',
                    'message' => 'The page may have moved, or the address may be mistyped.',
                    'seo' => ['title' => 'Page not found', 'robots' => 'noindex, follow'],
                ], 'layouts/site');
                return Response::html($html, 404)->header('X-Robots-Tag', 'noindex');
            }
            if ($status === 503) {
                SiteContext::boot($this->request);
                return Response::html(View::render('site/maintenance', ['title' => 'Back soon'], null), 503)->header('Retry-After', '600');
            }
        } catch (Throwable $e) {
            ErrorHandler::report($e);
        }
        $title = match ($status) {
            404 => 'Page not found',
            403 => 'Not allowed',
            405 => 'Method not allowed',
            419 => 'Page expired',
            429 => 'Too many requests',
            503 => 'Back soon',
            default => 'Something went wrong',
        };
        return Response::html(
            '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title>'
            . '<div style="font-family:system-ui;max-width:32rem;margin:15vh auto;padding:1rem;text-align:center">'
            . '<h1 style="font-size:1.5rem">' . e($title) . '</h1><p><a href="/">Go to the homepage</a></p></div>',
            $status
        );
    }
}
