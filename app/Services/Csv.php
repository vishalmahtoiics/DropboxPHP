<?php

declare(strict_types=1);

namespace App\Services;

final class Csv
{
    /**
     * Neutralise spreadsheet formula injection: a cell starting with = + - @
     * (or a tab/CR) would be executed by Excel, so it is prefixed with '.
     */
    public static function safe(?string $value): string
    {
        $value = (string) $value;
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }

    /** @param list<string> $headers @param iterable<list<mixed>> $rows */
    public static function build(array $headers, iterable $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, array_map(static fn ($v) => self::safe($v === null ? '' : (string) $v), $row));
        }
        rewind($out);
        return "\xEF\xBB\xBF" . stream_get_contents($out);
    }
}
