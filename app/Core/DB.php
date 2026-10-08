<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use PDO;
use PDOStatement;
use Throwable;

/**
 * The database, through PDO with native prepared statements.
 *
 * Every value goes through a placeholder. Identifiers (table and column names)
 * can never be bound, so the helpers that take them check each one against a
 * strict pattern and refuse anything else — they are only ever passed code
 * literals or whitelisted sort keys, never request input.
 */
final class DB
{
    private static ?PDO $pdo = null;
    private static int $transactionDepth = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = config('database');
            if ($c['database'] === '') {
                throw new \RuntimeException('The site is not installed yet.');
            }
            $dsn = $c['socket'] !== ''
                ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $c['socket'], $c['database'], $c['charset'])
                : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['database'], $c['charset']);
            self::$pdo = new PDO($dsn, $c['username'], $c['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            ]);
            self::$pdo->exec("SET NAMES {$c['charset']} COLLATE {$c['collation']}");
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
            self::$pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        }
        return self::$pdo;
    }

    public static function setPdo(?PDO $pdo): void
    {
        self::$pdo = $pdo;
        self::$transactionDepth = 0;
    }

    /** @param array<int|string, mixed> $params */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, is_bool($value) ? (int) $value : $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /** @return array<string, mixed>|null */
    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** @return list<mixed> */
    public static function column(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @param array<string, mixed> $data */
    public static function insert(string $table, array $data): int
    {
        self::assertIdentifier($table);
        $columns = array_keys($data);
        array_walk($columns, [self::class, 'assertIdentifier']);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn ($c) => "`$c`", $columns)),
            implode(', ', array_map(static fn ($c) => ":$c", $columns))
        );
        self::query($sql, self::normalise($data));
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where column => value, ANDed
     */
    public static function update(string $table, array $data, array $where): int
    {
        if ($data === []) {
            return 0;
        }
        self::assertIdentifier($table);
        $set = [];
        $params = [];
        foreach ($data as $column => $value) {
            self::assertIdentifier($column);
            $set[] = "`$column` = :s_$column";
            $params["s_$column"] = $value;
        }
        [$clause, $whereParams] = self::whereClause($where);
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $set), $clause);
        return self::query($sql, self::normalise($params + $whereParams))->rowCount();
    }

    /** @param array<string, mixed> $where */
    public static function delete(string $table, array $where): int
    {
        self::assertIdentifier($table);
        if ($where === []) {
            throw new InvalidArgumentException('Refusing to delete without a condition.');
        }
        [$clause, $params] = self::whereClause($where);
        return self::query("DELETE FROM `$table` WHERE $clause", self::normalise($params))->rowCount();
    }

    /**
     * Insert, or update the given columns when a unique key already exists.
     *
     * @param array<string, mixed> $data
     * @param list<string> $updateColumns
     */
    public static function upsert(string $table, array $data, array $updateColumns): void
    {
        self::assertIdentifier($table);
        $columns = array_keys($data);
        array_walk($columns, [self::class, 'assertIdentifier']);
        array_walk($updateColumns, [self::class, 'assertIdentifier']);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            $table,
            implode(', ', array_map(static fn ($c) => "`$c`", $columns)),
            implode(', ', array_map(static fn ($c) => ":$c", $columns)),
            $updateColumns === []
                ? '`' . $columns[0] . '` = `' . $columns[0] . '`'
                : implode(', ', array_map(static fn ($c) => "`$c` = VALUES(`$c`)", $updateColumns))
        );
        self::query($sql, self::normalise($data));
    }

    /**
     * Run a callback in a transaction. Nested calls join the outer one.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        if (self::$transactionDepth === 0) {
            $pdo->beginTransaction();
        }
        self::$transactionDepth++;
        try {
            $result = $callback();
            self::$transactionDepth--;
            if (self::$transactionDepth === 0) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            self::$transactionDepth--;
            if (self::$transactionDepth === 0 && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** "?, ?, ?" for an IN list, with the values to bind. @return array{0:string,1:list<mixed>} */
    public static function in(array $values): array
    {
        $values = array_values($values);
        if ($values === []) {
            return ['NULL', []];
        }
        return [implode(', ', array_fill(0, count($values), '?')), $values];
    }

    public static function assertIdentifier(string $name): void
    {
        if (!preg_match('/^[a-z_][a-z0-9_]{0,63}$/i', $name)) {
            throw new InvalidArgumentException('Invalid SQL identifier.');
        }
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private static function whereClause(array $where): array
    {
        $parts = [];
        $params = [];
        foreach ($where as $column => $value) {
            self::assertIdentifier($column);
            if ($value === null) {
                $parts[] = "`$column` IS NULL";
            } else {
                $parts[] = "`$column` = :w_$column";
                $params["w_$column"] = $value;
            }
        }
        return [implode(' AND ', $parts), $params];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private static function normalise(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        return $data;
    }
}
