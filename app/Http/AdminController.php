<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Countries;

/**
 * Base for every admin screen and action.
 *
 * The constructor is the authentication boundary: an anonymous request gets
 * the site's own 404 (never a redirect that would name the sign-in screen),
 * and a half-authenticated one is sent to the MFA step it still owes. Each
 * action then calls authorize() for its own permission.
 */
abstract class AdminController extends Controller
{
    /** @var array<string, mixed> */
    protected array $me;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $state = Auth::state();
        if ($state['status'] === Auth::MFA_SETUP) {
            throw new RedirectException('/auth/setup-2fa');
        }
        if ($state['status'] === Auth::MFA_PENDING) {
            throw new RedirectException('/auth/verify-2fa');
        }
        if ($state['status'] !== Auth::AUTHENTICATED) {
            throw HttpException::notFound();
        }
        $this->me = $state['user'];
        View::share('me', $this->me);
        View::share('adminCountry', Countries::adminCurrent());
    }

    protected function authorize(string $permission): void
    {
        if (!Auth::can($permission)) {
            throw HttpException::forbidden();
        }
    }

    /** @param list<string> $permissions */
    protected function authorizeAny(array $permissions): void
    {
        if (!Auth::canAny($permissions)) {
            throw HttpException::forbidden();
        }
    }

    /** @param array<string, mixed> $data */
    protected function screen(string $template, array $data = []): Response
    {
        return Response::html(View::render('admin/' . $template, $data, 'layouts/admin'));
    }

    protected function id(string $value): int
    {
        if (!ctype_digit($value)) {
            throw HttpException::notFound();
        }
        return (int) $value;
    }
}
