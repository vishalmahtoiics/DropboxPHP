<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\DB;
use App\Core\Installation;
use App\Core\Migrator;
use App\Core\Permissions;
use App\Core\ValidationException;
use Database\Seeder;
use PDOException;
use RuntimeException;

/**
 * The browser installer: checks the server, connects to the database, builds
 * the schema, creates the first administrator and records the site's values.
 *
 * Against a database that already holds a site (moving hosts, or a lost
 * installation file) it creates nothing: the old encryption key is required,
 * because without it every MFA secret and stored password is unreadable.
 */
final class Installer
{
    public const EXTENSIONS = ['pdo_mysql', 'sodium', 'mbstring', 'fileinfo', 'curl', 'zip', 'dom', 'openssl'];

    /** Paths the installer may not use for the sign-in screen. */
    private const RESERVED = ['/', '/admin', '/install', '/media', '/assets', '/auth', '/preview', '/cron', '/health', '/ready', '/sitemap.xml', '/robots.txt'];

    /** @return list<array{0:string,1:bool,2:string}> label, ok, detail */
    public static function requirements(): array
    {
        $checks = [['PHP 8.1 or newer', PHP_VERSION_ID >= 80100, PHP_VERSION]];
        foreach (self::EXTENSIONS as $ext) {
            $checks[] = ["PHP extension: $ext", extension_loaded($ext), extension_loaded($ext) ? 'installed' : 'missing — enable it in your hosting panel (Select PHP Version → Extensions)'];
        }
        foreach (['storage', 'storage/uploads', 'storage/backups', 'storage/logs', 'storage/cache', 'storage/sessions'] as $dir) {
            $path = BASE_PATH . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $ok = is_dir($path) && is_writable($path);
            $checks[] = ["Writable folder: $dir", $ok, $ok ? 'writable' : 'not writable — set its permission to 775'];
        }
        return $checks;
    }

    public static function ready(): bool
    {
        foreach (self::requirements() as [, $ok]) {
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string, mixed> $in
     * @return array{key:string, existing:bool, login_path:string, url:string}
     */
    public static function install(array $in): array
    {
        if (!self::ready()) {
            throw new ValidationException([], 'Fix the server checks first.');
        }
        $lock = fopen(BASE_PATH . '/storage/install.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new ValidationException([], 'Another installation is running. Wait a moment and reload.');
        }
        try {
            Installation::reset();
            if (Installation::installed()) {
                throw new ValidationException([], 'The site is already installed.');
            }
            return self::run(self::validate($in));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink(BASE_PATH . '/storage/install.lock');
        }
    }

    /** @param array<string, mixed> $in @return array<string, mixed> */
    private static function validate(array $in): array
    {
        $errors = [];
        $str = static fn (string $k, int $max = 200) => mb_substr(trim((string) ($in[$k] ?? '')), 0, $max);

        $url = rtrim($str('url', 255), '/');
        if ($error = self::urlError($url)) {
            $errors['url'] = $error;
        }
        $db = [
            'host' => $str('db_host', 255) ?: 'localhost',
            'port' => (int) ($in['db_port'] ?? 3306) ?: 3306,
            'database' => $str('db_name', 64),
            'username' => $str('db_user', 80),
            'password' => (string) ($in['db_pass'] ?? ''),
            'socket' => '',
        ];
        if (!preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $db['database'])) {
            $errors['db_name'] = 'Enter the database name from your hosting panel.';
        }
        if ($db['username'] === '') {
            $errors['db_user'] = 'Enter the database user.';
        }
        if (!preg_match('/^[A-Za-z0-9.\-_:\[\]]+$/', $db['host'])) {
            $errors['db_host'] = 'Enter a host name such as localhost.';
        }
        if ($db['port'] < 1 || $db['port'] > 65535) {
            $errors['db_port'] = 'Enter a valid port (usually 3306).';
        }

        $login = '/' . trim(strtolower($str('login_path', 80)), '/');
        if ($error = self::loginPathError($login)) {
            $errors['login_path'] = $error;
        }
        $tz = $str('timezone', 64);
        if (!in_array($tz, timezone_identifiers_list(), true)) {
            $errors['timezone'] = 'Choose a timezone.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        return [
            'url' => $url, 'db' => $db, 'login_path' => $login, 'timezone' => $tz,
            'demo' => !empty($in['demo']),
            'admin_name' => $str('admin_name', 120),
            'admin_email' => strtolower($str('admin_email', 254)),
            'admin_password' => (string) ($in['admin_password'] ?? ''),
            'admin_password_confirmation' => (string) ($in['admin_password_confirmation'] ?? ''),
            'existing_key' => trim((string) ($in['existing_key'] ?? '')),
        ];
    }

    public static function urlError(string $url): ?string
    {
        return preg_match('#^https?://[a-z0-9.-]+(:\d+)?(/[A-Za-z0-9._~/-]*)?$#i', $url)
            ? null : 'Enter the full address, e.g. https://www.example.com';
    }

    public static function loginPathError(string $path): ?string
    {
        if (!preg_match('#^/[a-z0-9][a-z0-9/_-]{2,79}$#', $path) || str_contains($path, '//')) {
            return 'Use letters, numbers, - and / only, e.g. /team-login';
        }
        return self::reserved($path) ? 'That address is used by the site. Choose another.' : null;
    }

    private static function reserved(string $path): bool
    {
        foreach (self::RESERVED as $r) {
            if ($path === $r || ($r !== '/' && str_starts_with($path, $r . '/'))) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, mixed> $v @return array{key:string, existing:bool, login_path:string, url:string} */
    private static function run(array $v): array
    {
        Config::set('database', $v['db'] + ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']);
        DB::setPdo(null);
        try {
            DB::pdo();
        } catch (PDOException $e) {
            DB::setPdo(null);
            throw ValidationException::with('db_host', self::connectionError($e));
        }
        date_default_timezone_set($v['timezone']);

        $existing = (bool) DB::value("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")
            && (int) DB::value('SELECT COUNT(*) FROM users') > 0;

        if ($existing) {
            $key = $v['existing_key'];
            if (!preg_match('#^base64:[A-Za-z0-9+/]{43}=$#', $key)) {
                throw ValidationException::with('existing_key', 'This database already has a site. Enter the encryption key you saved when it was installed (it starts with base64:).');
            }
            Config::set('app.key', $key);
            $hasCheck = (bool) DB::value("SELECT COUNT(*) FROM settings WHERE `group` = '_install'");
            if ($hasCheck && !Installation::keyMatches()) {
                throw ValidationException::with('existing_key', 'That key does not belong to this database.');
            }
            Migrator::migrate();
            Permissions::sync();
            Installation::storeKeyCheck();
        } else {
            $errors = [];
            if ($v['admin_name'] === '') {
                $errors['admin_name'] = 'Enter your name.';
            }
            if (!filter_var($v['admin_email'], FILTER_VALIDATE_EMAIL)) {
                $errors['admin_email'] = 'Enter a valid email address.';
            }
            if ($error = Auth::passwordError($v['admin_password'], $v['admin_email'])) {
                $errors['admin_password'] = $error;
            } elseif (!hash_equals($v['admin_password'], $v['admin_password_confirmation'])) {
                $errors['admin_password_confirmation'] = 'The passwords do not match.';
            }
            if ($errors) {
                throw new ValidationException($errors);
            }
            $key = Installation::newKey();
            Config::set('app.key', $key);
            require_once BASE_PATH . '/database/Seeder.php';
            (new Seeder(static fn (string $line) => null, $v['demo']))->run();
            $roleId = (int) DB::value("SELECT id FROM roles WHERE slug = 'super-admin'");
            $adminId = DB::insert('users', [
                'email' => $v['admin_email'], 'name' => $v['admin_name'], 'password_hash' => Auth::hash($v['admin_password']),
                'role_id' => $roleId, 'status' => 'ACTIVE', 'password_changed_at' => now(),
            ]);
            Installation::storeKeyCheck();
        }

        try {
            Installation::write([
                'url' => $v['url'],
                'key' => $key,
                'cron_secret' => Installation::newCronSecret(),
                'login_path' => $v['login_path'],
                'timezone' => $v['timezone'],
                'database' => $v['db'],
                'installed_at' => date('c'),
            ]);
        } catch (RuntimeException $e) {
            if (!$existing) {
                // Leave the database as a fresh one so the next attempt is too.
                DB::delete('users', ['id' => $adminId]);
                DB::query("DELETE FROM settings WHERE `group` = '_install'");
            }
            throw new ValidationException([], $e->getMessage() . ' Set the storage folder permission to 775 and try again.');
        }
        return ['key' => $key, 'existing' => $existing, 'login_path' => $v['login_path'], 'url' => $v['url']];
    }

    private static function connectionError(PDOException $e): string
    {
        $code = (int) ($e->errorInfo[1] ?? $e->getCode());
        return match (true) {
            $code === 1045 => 'The database user or password is wrong.',
            $code === 1044, $code === 1049 => 'That database does not exist, or the user has no access to it. In your hosting panel, add the user to the database with ALL PRIVILEGES.',
            $code === 2002, $code === 2005, $code === 2006 => 'Could not reach the database server. On most hosting it is "localhost".',
            default => 'Could not connect to the database. Check the details and try again.',
        };
    }
}
