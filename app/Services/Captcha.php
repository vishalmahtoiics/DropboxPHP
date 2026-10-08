<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;

/**
 * A self-hosted arithmetic challenge for forms that require one. The answer
 * is never sent to the browser: the token carries an HMAC over the form, the
 * answer and the issue time, checked on submit. No third-party service, no
 * cookies.
 */
final class Captcha
{
    /** @return array{question:string, token:string} */
    public static function challenge(int $formId): array
    {
        $a = random_int(2, 9);
        $b = random_int(1, 9);
        $time = time();
        $mac = Crypto::hash($formId . '|' . ($a + $b) . '|' . $time, 'captcha', 40);
        return ['question' => "What is $a + $b?", 'token' => $time . '.' . $mac];
    }

    public static function verify(int $formId, string $token, string $answer): bool
    {
        if (!preg_match('/^(\d{10})\.([a-f0-9]{40})$/', $token, $m) || !preg_match('/^\d{1,3}$/', trim($answer))) {
            return false;
        }
        if (time() - (int) $m[1] > 7200) {
            return false;
        }
        return hash_equals(Crypto::hash($formId . '|' . (int) trim($answer) . '|' . $m[1], 'captcha', 40), $m[2]);
    }
}
