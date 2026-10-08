<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Symmetric encryption (libsodium XChaCha20-Poly1305, authenticated) and keyed
 * hashing, both derived from the site's encryption key (created by the
 * installer) with domain separation so one key never does two jobs.
 */
final class Crypto
{
    private const PREFIX = 'enc:v1:';

    public static function key(string $purpose): string
    {
        $raw = (string) config('app.key');
        if (str_starts_with($raw, 'base64:')) {
            $raw = (string) base64_decode(substr($raw, 7), true);
        }
        if (strlen($raw) < 16) {
            throw new RuntimeException('The encryption key is missing. Open the website to run the installer.');
        }
        return hash_hmac('sha256', $purpose, $raw, true);
    }

    public static function encrypt(string $plain, string $purpose = 'data'): string
    {
        $key = self::key('encrypt:' . $purpose);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, $purpose, $nonce, $key);
        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    public static function decrypt(?string $payload, string $purpose = 'data'): ?string
    {
        if ($payload === null || !str_starts_with($payload, self::PREFIX)) {
            return null;
        }
        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        $nlen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if ($raw === false || strlen($raw) <= $nlen) {
            return null;
        }
        $key = self::key('encrypt:' . $purpose);
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw, $nlen), $purpose, substr($raw, 0, $nlen), $key);
        return $plain === false ? null : $plain;
    }

    /** Keyed hash, e.g. for IP addresses: enough to match, useless to reverse. */
    public static function hash(string $value, string $purpose = 'hash', int $length = 32): string
    {
        return substr(hash_hmac('sha256', $value, self::key('hash:' . $purpose)), 0, $length);
    }

    public static function token(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
