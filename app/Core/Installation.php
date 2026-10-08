<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * The per-site values the web installer records: site address, database
 * connection, encryption key, sign-in path and timezone.
 *
 * They live in storage/installed.php, written only by the installer and by
 * Admin → Website settings → System — nobody edits it by hand. storage/ sits
 * outside the web root and is refused by .htaccess, and the file is 0600.
 */
final class Installation
{
    public const DEFAULT_LOGIN_PATH = '/auth-control-panel/admin';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    private static ?string $file = null;

    public static function file(): string
    {
        return self::$file ?? BASE_PATH . '/storage/installed.php';
    }

    /** For tests: read and write another file (null = the real one). */
    public static function useFile(?string $file): void
    {
        self::$file = $file;
        self::$cache = null;
        Config::reset();
    }

    /** @return array<string, mixed> */
    public static function read(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $file = self::file();
        $data = is_file($file) ? require $file : [];
        return self::$cache = is_array($data) ? $data : [];
    }

    public static function installed(): bool
    {
        $data = self::read();
        return ($data['key'] ?? '') !== '' && ($data['database']['database'] ?? '') !== '';
    }

    /**
     * Merge $changes into the stored values and write them atomically.
     *
     * @param array<string, mixed> $changes
     */
    public static function write(array $changes): void
    {
        $data = array_replace(self::read(), $changes);
        $php = "<?php\n\n// Written by the installer. Change these values from\n// Admin → Website settings → System, not by hand.\n\nreturn " . var_export($data, true) . ";\n";
        $file = self::file();
        $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmp, $php, LOCK_EX) === false) {
            throw new RuntimeException('The storage folder is not writable.');
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('Could not save the installation settings.');
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
        self::$cache = null;
        Config::reset();
    }

    public static function newKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    public static function newCronSecret(): string
    {
        return bin2hex(random_bytes(24));
    }

    /**
     * A fingerprint of the encryption key kept in the database, so a
     * re-install against an existing database can prove it has the right key
     * (otherwise every MFA secret and stored password would be unreadable).
     */
    public static function storeKeyCheck(): void
    {
        DB::upsert('settings', ['group' => '_install', 'data' => ['key_check' => Crypto::hash('installation', 'key-check', 64)]], ['data']);
    }

    public static function keyMatches(): bool
    {
        $raw = DB::value("SELECT data FROM settings WHERE `group` = '_install'");
        $stored = is_string($raw) ? (json_decode($raw, true)['key_check'] ?? '') : '';
        return is_string($stored) && $stored !== '' && hash_equals($stored, Crypto::hash('installation', 'key-check', 64));
    }

    /** For tests. */
    public static function reset(): void
    {
        self::$cache = null;
    }
}
