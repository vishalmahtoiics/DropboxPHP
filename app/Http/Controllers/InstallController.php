<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Installation;
use App\Core\Response;
use App\Core\View;
use App\Http\Controller;
use App\Services\Installer;

/**
 * /install — only reachable until the site is installed; the Kernel sends
 * every other request here until then, and nothing reaches it afterwards.
 */
final class InstallController extends Controller
{
    public function show(): Response
    {
        $scheme = $this->request->isSecure() ? 'https' : 'http';
        return $this->page('install/form', [
            'title' => 'Install',
            'checks' => Installer::requirements(),
            'ready' => Installer::ready(),
            'defaults' => [
                'url' => $scheme . '://' . $this->request->host(),
                'db_host' => 'localhost',
                'db_port' => '3306',
                'login_path' => Installation::DEFAULT_LOGIN_PATH,
                'timezone' => 'Asia/Kolkata',
            ],
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function submit(): Response
    {
        $result = Installer::install($this->request->body);
        return $this->page('install/done', ['title' => 'Installed'] + $result);
    }

    /** @param array<string, mixed> $data */
    private function page(string $template, array $data): Response
    {
        return Response::html(View::render($template, $data, 'layouts/install'))
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
