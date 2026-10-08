<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\DB;

/**
 * Forms as the public site sees them, and the signed token every rendered
 * form carries.
 *
 * Public forms do not use the admin session's CSRF token (public pages never
 * open a session). Instead each render embeds an HMAC over the form id and
 * the render time. The server checks the signature, that the form was on
 * screen for at least 1.2 s (bots submit instantly) and at most a day.
 */
final class Forms
{
    public const FIELD_TYPES = [
        'TEXT' => 'Short text', 'NAME' => 'Full name', 'EMAIL' => 'Email', 'PHONE' => 'Phone', 'COMPANY' => 'Company',
        'NUMBER' => 'Number', 'TEXTAREA' => 'Long text', 'SELECT' => 'Dropdown', 'MULTISELECT' => 'Multiple choice (checkboxes)',
        'RADIO' => 'Single choice (radio)', 'CHECKBOX' => 'Checkbox', 'DATE' => 'Date', 'TIME' => 'Time', 'URL' => 'Website URL',
        'HIDDEN' => 'Hidden value', 'CONSENT' => 'Consent checkbox',
    ];

    /** @var array<string, array<string,mixed>|null> */
    private static array $cache = [];

    /** An active form with its enabled fields, or null. */
    public static function bySlug(string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }
        if (array_key_exists($slug, self::$cache)) {
            return self::$cache[$slug];
        }
        $form = DB::first('SELECT * FROM forms WHERE slug = ? AND is_active = 1 AND deleted_at IS NULL', [$slug]);
        if ($form) {
            $form['fields'] = self::fields((int) $form['id'], true);
            $form['design'] = json_decode((string) ($form['design'] ?? ''), true) ?: [];
        }
        return self::$cache[$slug] = $form;
    }

    public static function byId(int $id): ?array
    {
        $slug = DB::value('SELECT slug FROM forms WHERE id = ?', [$id]);
        return is_string($slug) ? self::bySlug($slug) : null;
    }

    /** @return list<array<string,mixed>> */
    public static function fields(int $formId, bool $enabledOnly = false): array
    {
        $rows = DB::all('SELECT * FROM form_fields WHERE form_id = ?' . ($enabledOnly ? ' AND is_enabled = 1' : '') . ' ORDER BY sort_order, id', [$formId]);
        foreach ($rows as &$row) {
            $row['options'] = json_decode((string) ($row['options'] ?? ''), true) ?: [];
        }
        return $rows;
    }

    public static function token(int $formId, ?int $time = null): string
    {
        $time ??= time();
        return $time . '.' . Crypto::hash($formId . '|' . $time, 'form-token', 40);
    }

    /** 'ok', 'fast', 'expired' or 'invalid'. */
    public static function checkToken(int $formId, string $token, ?float $now = null): string
    {
        if (!preg_match('/^(\d{10})\.([a-f0-9]{40})$/', $token, $m)) {
            return 'invalid';
        }
        if (!hash_equals(Crypto::hash($formId . '|' . $m[1], 'form-token', 40), $m[2])) {
            return 'invalid';
        }
        $age = ($now ?? microtime(true)) - (int) $m[1];
        if ($age < 1.2) {
            return 'fast';
        }
        return $age > 86400 ? 'expired' : 'ok';
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
