<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\QrCode;
use App\Core\RateLimiter;
use App\Core\Response;
use App\Core\Session;
use App\Core\Totp;
use App\Core\View;
use App\Http\Controller;
use App\Services\Mfa;

/**
 * The two-factor steps between a correct password and the admin.
 * Anonymous visitors get a 404 here too, for the same reason /admin does.
 */
final class MfaController extends Controller
{
    /** @return array<string,mixed> */
    private function partialUser(string $expected): array
    {
        $state = Auth::state();
        if ($state['status'] === Auth::ANONYMOUS) {
            throw HttpException::notFound();
        }
        if ($state['status'] === Auth::AUTHENTICATED) {
            throw new \App\Http\RedirectException('/admin');
        }
        if ($state['status'] !== $expected) {
            throw new \App\Http\RedirectException($state['status'] === Auth::MFA_SETUP ? '/auth/setup-2fa' : '/auth/verify-2fa');
        }
        return $state['user'];
    }

    public function setup(): Response
    {
        $user = $this->partialUser(Auth::MFA_SETUP);
        $secret = Mfa::pendingSecret((int) $user['id']);
        $uri = Totp::uri($secret, $user['email'], Mfa::issuer());
        return Response::html(View::render('auth/setup-2fa', [
            'title' => 'Set up two-factor authentication',
            'user' => $user,
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'qr' => QrCode::svg($uri, 5),
        ], 'layouts/auth'));
    }

    public function confirmSetup(): Response
    {
        $user = $this->partialUser(Auth::MFA_SETUP);
        $this->throttle((int) $user['id']);
        $codes = Mfa::confirmEnrolment((int) $user['id'], $this->request->str('code'));
        if ($codes === null) {
            Session::flash('error', 'That code did not match. Check the time on your phone and try the newest code.');
            return $this->redirect('/auth/setup-2fa');
        }
        Auth::markMfaVerified('totp');
        Audit::record('mfa-enrolled', 'User', $user['id']);
        Session::flash('recovery_codes', $codes);
        return $this->redirect('/auth/recovery-codes');
    }

    public function verify(): Response
    {
        $user = $this->partialUser(Auth::MFA_PENDING);
        return Response::html(View::render('auth/verify-2fa', ['title' => 'Two-factor authentication', 'user' => $user], 'layouts/auth'));
    }

    public function confirmVerify(): Response
    {
        $user = $this->partialUser(Auth::MFA_PENDING);
        $this->throttle((int) $user['id']);
        $method = Mfa::verify((int) $user['id'], $this->request->str('code'));
        if ($method === null) {
            Session::flash('error', 'That code is not valid. Enter the current 6-digit code, or one of your recovery codes.');
            return $this->redirect('/auth/verify-2fa');
        }
        Auth::markMfaVerified($method);
        RateLimiter::clear('mfa:' . $user['id']);
        Audit::record('signed-in', 'User', $user['id'], 'Second factor verified (' . $method . ')');
        if ($method === 'recovery') {
            $left = Mfa::remainingRecoveryCodes((int) $user['id']);
            Session::flash('warning', "You used a recovery code. $left left — generate new ones from your profile if you are running low.");
        }
        return $this->redirect('/admin');
    }

    public function recoveryCodes(): Response
    {
        $state = Auth::state();
        if ($state['status'] !== Auth::AUTHENTICATED) {
            throw HttpException::notFound();
        }
        $codes = Session::getFlash('recovery_codes');
        if (!is_array($codes)) {
            return $this->redirect('/admin/profile');
        }
        return Response::html(View::render('auth/recovery-codes', ['title' => 'Save your recovery codes', 'codes' => $codes], 'layouts/auth'));
    }

    private function throttle(int $userId): void
    {
        if (!RateLimiter::hit('mfa:' . $userId, 6, 600)) {
            Session::flash('error', 'Too many attempts. Wait 10 minutes and try again.');
            throw new \App\Http\RedirectException($this->request->path);
        }
    }
}
