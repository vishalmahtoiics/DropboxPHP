<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;

abstract class Controller
{
    public function __construct(protected Request $request)
    {
    }

    /** @param array<string, string> $rules @return array<string, mixed> */
    protected function validate(array $rules, array $labels = []): array
    {
        return Validator::make($this->request->body, $rules, $labels);
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to, 303);
    }

    protected function back(string $fallback = '/admin'): Response
    {
        return Response::back($this->request, $fallback);
    }

    protected function flash(string $message, string $type = 'success'): void
    {
        Session::flash($type, $message);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /** @param array<string, mixed> $data */
    protected function render(string $template, array $data = [], ?string $layout = null): Response
    {
        return Response::html(View::render($template, $data, $layout));
    }

    protected function user(): ?array
    {
        return Auth::user();
    }

    protected function notFound(): never
    {
        throw HttpException::notFound();
    }
}
