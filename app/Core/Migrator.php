<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Applies database/migrations/*.sql in filename order, each exactly once.
 *
 * Like `prisma migrate deploy`, it only ever applies files that exist — it
 * never generates, resets or drops anything — so it is safe to run on every
 * deploy. Statements are split on semicolons at the end of a line; the files
 * contain no procedures or triggers.
 */
final class Migrator
{
    public static function ensureTable(): void
    {
        DB::query('CREATE TABLE IF NOT EXISTS migrations (
            name       VARCHAR(190) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    /** @return list<string> */
    public static function pending(): array
    {
        self::ensureTable();
        $applied = DB::column('SELECT name FROM migrations');
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        sort($files, SORT_STRING);
        return array_values(array_filter(
            array_map('basename', $files),
            static fn (string $name) => !in_array($name, $applied, true)
        ));
    }

    /**
     * @param callable(string): void|null $log
     * @return list<string> the migrations applied
     */
    public static function migrate(?callable $log = null): array
    {
        $done = [];
        foreach (self::pending() as $name) {
            $sql = (string) file_get_contents(BASE_PATH . '/database/migrations/' . $name);
            foreach (self::statements($sql) as $statement) {
                DB::pdo()->exec($statement);
            }
            DB::insert('migrations', ['name' => $name]);
            $done[] = $name;
            if ($log) {
                $log($name);
            }
        }
        return $done;
    }

    /** @return list<string> */
    public static function statements(string $sql): array
    {
        $lines = [];
        foreach (preg_split('/\R/', $sql) ?: [] as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '--')) {
                continue;
            }
            $lines[] = $line;
        }
        $parts = preg_split('/;\s*$/m', implode("\n", $lines)) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn ($s) => $s !== ''));
    }
}
