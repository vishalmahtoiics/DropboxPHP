<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Crypto;
use App\Core\ErrorHandler;
use App\Core\RateLimiter;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controller;

/**
 * The sign-in screen. It lives at LOGIN_PATH (not /login), and signed-out
 * requests to /admin get a 404 rather than a redirect here, so the address
 * is never advertised.
 */
final class LoginController extends Controller
{
    public function show(): Response
    {
        $state = Auth::state()['status'];
        if ($state === Auth::AUTHENTICATED) {
            return $this->redirect('/admin');
        }
        if ($state === Auth::MFA_SETUP) {
            return $this->redirect('/auth/setup-2fa');
        }
        if ($state === Auth::MFA_PENDING) {
            return $this->redirect('/auth/verify-2fa');
        }
        return Response::html(View::render('auth/login', ['title' => 'Sign in'], 'layouts/auth'))
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function login(): Response
    {
        $email = strtolower(mb_substr($this->request->str('email'), 0, 254));
        $password = (string) ($this->request->body['password'] ?? '');
        $ip = $this->request->ip();

        $emailKey = 'login:email:' . $email;
        $ipKey = 'login:ip:' . $ip;
        if (RateLimiter::tooMany($emailKey, 8) || RateLimiter::tooMany($ipKey, 20)) {
            ErrorHandler::log('login throttled for ' . Crypto::hash($email, 'log', 12) . ' from ' . Crypto::hash($ip, 'ip', 12), 'security');
            return $this->fail('Too many attempts. Please wait 15 minutes and try again.', $email);
        }

        $user = $email !== '' && $password !== '' && strlen($password) <= 200 ? Auth::attempt($email, $password) : null;
        if (!$user) {
            RateLimiter::hit($emailKey, 8, 900);
            RateLimiter::hit($ipKey, 20, 900);
            ErrorHandler::log('login failed for ' . Crypto::hash($email, 'log', 12) . ' from ' . Crypto::hash($ip, 'ip', 12), 'security');
            return $this->fail('That email and password do not match an active account.', $email);
        }

        RateLimiter::clear($emailKey);
        Auth::login($user, $this->request);
        Audit::record('signed-in', 'User', $user['id'], 'Password accepted');
        return $this->redirect('/admin');
    }

    public function logout(): Response
    {
        $user = Auth::state()['user'];
        if ($user) {
            Audit::record('signed-out', 'User', $user['id']);
        }
        Auth::logout();
        return $this->redirect((string) config('app.login_path'));
    }

    private function fail(string $message, string $email): Response
    {
        Session::flash('error', $message);
        Session::flash('_old', ['email' => $email]);
        return $this->redirect((string) config('app.login_path'));
    }
}
