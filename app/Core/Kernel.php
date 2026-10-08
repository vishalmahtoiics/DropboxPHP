<?php

declare(strict_types=1);

namespace App\Core;

use App\Http\Controllers\InstallController;
use App\Http\Controllers\Site\ErrorController;
use App\Services\Attribution;
use App\Services\Settings;
use Throwable;

/**
 * The request pipeline:
 *
 *   installer (until installed) → canonical host → session (admin/auth only)
 *     → CSRF → route → controller → error mapping → security headers
 *     → attribution cookies
 */
final class Kernel
{
    public function __construct(private Router $router)
    {
    }

    public static function surface(Request $request): string
    {
        $path = $request->path;
        $media = (string) config('app.media_path', '/media');
        if ($path === $media || str_starts_with($path, $media . '/')) {
            return 'media';
        }
        if (!Installation::installed()) {
            return 'admin';
        }
        if ($path === '/admin' || str_starts_with($path, '/admin/')
            || $path === config('app.login_path') || str_starts_with($path, '/auth/')
            || str_starts_with($path, '/preview/')) {
            return 'admin';
        }
        return 'site';
    }

    public function handle(Request $request): Response
    {
        Audit::setRequest($request);
        $surface = self::surface($request);

        try {
            if (!Installation::installed()) {
                $response = $this->installer($request);
                Security::apply($response, $request, $surface);
                return $response;
            }
            Request::trustProxies((string) Settings::get('website', 'trusted_proxies', ''));

            $canonical = $this->canonicalRedirect($request);
            if ($canonical !== null) {
                return Response::redirect($canonical, 308);
            }

            if ($surface === 'admin') {
                Session::start($request);
                if ($request->isPost() && !Csrf::verify($request)) {
                    throw new HttpException(419, 'Your session expired. Please reload the page and try again.');
                }
            }

            $response = $this->router->dispatch($request);
        } catch (\App\Http\RedirectException $e) {
            $response = Response::redirect($e->to, 303);
        } catch (ValidationException $e) {
            $response = $this->validationResponse($request, $e);
        } catch (HttpException $e) {
            $response = $this->httpErrorResponse($request, $e, $surface);
        } catch (Throwable $e) {
            ErrorHandler::report($e);
            if (config('app.debug')) {
                throw $e;
            }
            $response = $this->httpErrorResponse($request, new HttpException(500), $surface);
        }

        Security::apply($response, $request, $surface);
        if ($surface === 'site' && $request->method === 'GET' && $response->status === 200) {
            Attribution::capture($request, $response);
        }
        return $response;
    }

    /** Before installation only /install answers; everything else is sent there. */
    private function installer(Request $request): Response
    {
        if ($request->path !== '/install') {
            return Response::redirect('/install', 302)->header('Cache-Control', 'no-store');
        }
        try {
            Session::start($request);
            if (!$request->isPost()) {
                return (new InstallController($request))->show();
            }
            if (!Csrf::verify($request)) {
                Session::flash('error', 'The form expired. Please try again.');
                return Response::redirect('/install', 303);
            }
            return (new InstallController($request))->submit();
        } catch (ValidationException $e) {
            return $this->validationResponse($request, $e);
        } catch (Throwable $e) {
            ErrorHandler::report($e);
            Session::flash('error', 'Installation failed: ' . $e->getMessage());
            return Response::redirect('/install', 303);
        }
    }

    private function validationResponse(Request $request, ValidationException $e): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'error' => $e->getMessage(), 'fieldErrors' => $e->errors], 422);
        }
        $old = $request->body;
        unset($old['_token'], $old['password'], $old['password_confirmation'], $old['current_password'],
            $old['db_pass'], $old['admin_password'], $old['admin_password_confirmation'], $old['existing_key']);
        Session::flash('_errors', $e->errors);
        Session::flash('_old', $old);
        Session::flash('error', $e->getMessage());
        return Response::back($request);
    }

    private function httpErrorResponse(Request $request, HttpException $e, string $surface): Response
    {
        if ($request->wantsJson()) {
            $message = $e->status === 419 ? $e->getMessage() : match ($e->status) {
                403 => 'You do not have permission to do that.',
                404 => 'Not found.',
                405 => 'Method not allowed.',
                429 => $e->getMessage(),
                default => 'Something went wrong. Please try again.',
            };
            return Response::json(['ok' => false, 'error' => $message], $e->status);
        }
        if ($e->status === 419) {
            Session::flash('error', $e->getMessage());
            return Response::back($request, $request->path);
        }
        return (new ErrorController($request))->show($e->status, $surface);
    }

    /**
     * Bare domain ↔ www, to the host of the site address recorded at install.
     */
    private function canonicalRedirect(Request $request): ?string
    {
        if (!config('app.canonical_host_redirect') || PHP_SAPI === 'cli-server') {
            return null;
        }
        if (in_array($request->path, ['/health', '/ready'], true)) {
            return null;
        }
        $site = parse_url((string) config('app.url'));
        $want = strtolower((string) ($site['host'] ?? ''));
        $have = strtolower(explode(':', $request->host())[0]);
        if ($want === '' || $want === $have || in_array($have, ['localhost', '127.0.0.1'], true)) {
            return null;
        }
        $bare = static fn (string $h) => preg_replace('/^www\./', '', $h);
        if ($bare($want) !== $bare($have)) {
            return null; // An unrelated host (staging, IP): leave it alone.
        }
        $scheme = $site['scheme'] ?? 'https';
        $port = isset($site['port']) ? ':' . $site['port'] : '';
        return $scheme . '://' . $want . $port . ($request->server['REQUEST_URI'] ?? $request->path);
    }
}
