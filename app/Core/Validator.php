<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Declarative input validation, run on the server for every mutation.
 *
 *   $data = Validator::make($request->body, [
 *       'title' => 'required|string|max:200',
 *       'email' => 'required|email',
 *       'status' => 'in:DRAFT,PUBLISHED',
 *   ]);
 *
 * Returns only the declared keys, trimmed and cast. Throws
 * ValidationException with one message per failing field.
 */
final class Validator
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     * @return array<string, mixed>
     */
    public static function make(array $input, array $rules, array $labels = []): array
    {
        $clean = [];
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $label = $labels[$field] ?? ucfirst(str_replace(['_', '.'], ' ', $field));
            $rulesList = explode('|', $ruleString);
            $value = data_get($input, $field);
            $nullable = in_array('nullable', $rulesList, true);

            if (in_array('bool', $rulesList, true)) {
                $clean[$field] = in_array($value, [true, 1, '1', 'on', 'true', 'yes'], true);
                continue;
            }
            if (in_array('array', $rulesList, true)) {
                $clean[$field] = is_array($value) ? $value : [];
                if (in_array('required', $rulesList, true) && $clean[$field] === []) {
                    $errors[$field] = "$label is required.";
                }
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            } elseif ($value !== null && !is_scalar($value)) {
                $errors[$field] = "$label is invalid.";
                continue;
            }
            $value = $value === null ? '' : (string) $value;

            if ($value === '') {
                if (in_array('required', $rulesList, true)) {
                    $errors[$field] = "$label is required.";
                } else {
                    $clean[$field] = $nullable ? null : (in_array('int', $rulesList, true) ? null : '');
                }
                continue;
            }

            $error = null;
            foreach ($rulesList as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = self::check($name, $arg, $value, $label);
                if ($error !== null) {
                    break;
                }
            }
            if ($error !== null) {
                $errors[$field] = $error;
                continue;
            }
            $clean[$field] = match (true) {
                in_array('int', $rulesList, true) => (int) $value,
                in_array('email', $rulesList, true) => strtolower($value),
                default => $value,
            };
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return $clean;
    }

    private static function check(string $rule, ?string $arg, string $value, string $label): ?string
    {
        return match ($rule) {
            'required', 'nullable', 'string', 'bool', 'array' => null,
            'max' => mb_strlen($value) > (int) $arg ? "$label must be at most $arg characters." : null,
            'min' => mb_strlen($value) < (int) $arg ? "$label must be at least $arg characters." : null,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) && mb_strlen($value) <= 254 ? null : "$label must be a valid email address.",
            'url' => self::isUrl($value) ? null : "$label must be a valid URL (https://…) or a path starting with /.",
            'absurl' => preg_match('#^https?://[^\s]+$#i', $value) ? null : "$label must start with http:// or https://.",
            'int' => preg_match('/^-?\d+$/', $value) ? null : "$label must be a whole number.",
            'numeric' => is_numeric($value) ? null : "$label must be a number.",
            'decimal' => preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value) ? null : "$label must be an amount like 1299 or 1299.50.",
            'between' => self::between($value, (string) $arg) ? null : "$label is out of range.",
            'in' => in_array($value, explode(',', (string) $arg), true) ? null : "$label has an invalid value.",
            'slug' => preg_match('#^[a-z0-9]+(?:[-/][a-z0-9]+)*$#', $value) ? null : "$label may contain only lowercase letters, numbers, hyphens and slashes.",
            'key' => preg_match('/^[a-z][a-z0-9_]{0,63}$/', $value) ? null : "$label must start with a letter and use only a-z, 0-9 and _.",
            'date' => strtotime($value) !== false ? null : "$label must be a valid date.",
            'color' => Sanitizer::color($value) !== '' ? null : "$label must be a colour like #0061FF.",
            'phone' => preg_match('/^[+()\d\s.\-]{5,25}$/', $value) ? null : "$label must be a valid phone number.",
            'regex' => @preg_match((string) $arg, $value) === 1 ? null : "$label has an invalid format.",
            default => null,
        };
    }

    public static function isUrl(string $value): bool
    {
        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            return true;
        }
        if (str_starts_with($value, '#') || str_starts_with($value, 'mailto:') || str_starts_with($value, 'tel:')) {
            return true;
        }
        return (bool) preg_match('#^https?://[^\s<>"]+$#i', $value);
    }

    private static function between(string $value, string $arg): bool
    {
        [$min, $max] = array_map('floatval', explode(',', $arg . ',0'));
        return is_numeric($value) && (float) $value >= $min && (float) $value <= $max;
    }
}
