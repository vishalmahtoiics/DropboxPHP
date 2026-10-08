<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\ErrorHandler;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Full-site backups: the database (dumped by PHP itself, so it works on hosts
 * without shell access or mysqldump) plus the media library, in one ZIP with
 * a manifest and a SHA-256 checksum.
 *
 * A restore verifies the checksum before touching anything, takes a safety
 * backup first, puts the public site into maintenance mode while it runs,
 * and is serialised by a lock so two can never overlap.
 */
final class Backups
{
    /** Never dumped or restored: backup bookkeeping and throwaway state. */
    private const SKIP_TABLES = ['backups', 'backup_restores', 'system_locks', 'rate_limits', 'migrations'];
    private const FORMAT = 1;

    public static function dir(): string
    {
        $dir = (string) config('app.backup_dir');
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('The backup directory is not writable: ' . $dir);
        }
        $guard = $dir . '/.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, "Require all denied\nDeny from all\n");
        }
        return $dir;
    }

    public static function path(array $backup): string
    {
        $name = basename((string) $backup['filename']);
        if (!preg_match('/^backup-[\w\-]+\.zip$/', $name)) {
            throw new RuntimeException('Invalid backup file name.');
        }
        return self::dir() . '/' . $name;
    }

    /** @param 'FULL'|'DATABASE'|'MEDIA' $type */
    public static function create(string $type, string $origin, ?int $userId, string $note = ''): int
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required for backups.');
        }
        self::lock('backup', 3600);
        $filename = 'backup-' . date('Ymd-His') . '-' . strtolower($type) . '-' . bin2hex(random_bytes(4)) . '.zip';
        $id = DB::insert('backups', ['filename' => $filename, 'type' => $type, 'status' => 'RUNNING', 'origin' => $origin, 'created_by_id' => $userId, 'started_at' => now(), 'note' => $note ?: null]);
        @set_time_limit(0);
        $path = self::dir() . '/' . $filename;
        $sqlFile = null;
        try {
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('Could not create the archive.');
            }
            $stats = ['tables' => 0, 'rows' => 0, 'media' => 0];
            if ($type !== 'MEDIA') {
                $sqlFile = (string) tempnam(sys_get_temp_dir(), 'dbx');
                [$stats['tables'], $stats['rows']] = self::dumpDatabase($sqlFile);
                $zip->addFile($sqlFile, 'database.sql');
            }
            if ($type !== 'DATABASE') {
                $stats['media'] = self::addMedia($zip);
            }
            $zip->addFromString('manifest.json', (string) json_encode([
                'format' => self::FORMAT, 'app' => config('app.name'), 'version' => config('app.version'), 'type' => $type,
                'created_at' => date('c'), 'tables' => $stats['tables'], 'rows' => $stats['rows'], 'media' => $stats['media'],
                'migrations' => DB::column('SELECT name FROM migrations ORDER BY name'),
            ], JSON_PRETTY_PRINT));
            $zip->close();
            @chmod($path, 0640);
            DB::update('backups', [
                'status' => 'COMPLETED', 'size' => filesize($path), 'checksum' => hash_file('sha256', $path), 'tables_count' => $stats['tables'],
                'rows_count' => $stats['rows'], 'media_count' => $stats['media'], 'completed_at' => now(),
            ], ['id' => $id]);
            return $id;
        } catch (Throwable $e) {
            @unlink($path);
            DB::update('backups', ['status' => 'FAILED', 'error' => mb_substr($e->getMessage(), 0, 2000), 'completed_at' => now()], ['id' => $id]);
            ErrorHandler::report($e);
            throw $e;
        } finally {
            if ($sqlFile) {
                @unlink($sqlFile);
            }
            self::unlock('backup');
        }
    }

    /** @return array{0:int,1:int} tables, rows */
    private static function dumpDatabase(string $file): array
    {
        $out = fopen($file, 'wb');
        $pdo = DB::pdo();
        fwrite($out, "-- Website backup " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n");
        $tables = 0;
        $rows = 0;
        foreach (DB::column("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $table) {
            if (in_array($table, self::SKIP_TABLES, true) || !preg_match('/^[a-z0-9_]+$/', (string) $table)) {
                continue;
            }
            $tables++;
            $create = (string) (DB::first("SHOW CREATE TABLE `$table`")['Create Table'] ?? '');
            // One statement per line: newlines inside the DDL are collapsed and every
            // value is quoted by PDO (which escapes newlines), so splitting on a
            // trailing ";" at end of line is exact.
            fwrite($out, "DROP TABLE IF EXISTS `$table`;\n" . preg_replace('/\s*\n\s*/', ' ', $create) . ";\n");
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $batch = [];
            $columns = null;
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $columns ??= '(`' . implode('`,`', array_keys($row)) . '`)';
                $batch[] = '(' . implode(',', array_map(static fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)) . ')';
                $rows++;
                if (count($batch) >= 200) {
                    fwrite($out, "INSERT INTO `$table` $columns VALUES " . implode(',', $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                fwrite($out, "INSERT INTO `$table` $columns VALUES " . implode(',', $batch) . ";\n");
            }
        }
        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($out);
        return [$tables, $rows];
    }

    private static function addMedia(ZipArchive $zip): int
    {
        $root = realpath((string) config('app.upload_dir'));
        if ($root === false) {
            return 0;
        }
        $count = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if ($file->isFile() && preg_match(Storage::KEY_PATTERN, $rel)) {
                $zip->addFile($file->getPathname(), 'media/' . $rel);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Restore a completed backup. Runs a safety backup first.
     * @return int the safety backup id
     */
    public static function restore(int $backupId, int $userId): int
    {
        $backup = DB::first("SELECT * FROM backups WHERE id = ? AND status = 'COMPLETED'", [$backupId]);
        if (!$backup) {
            throw new RuntimeException('That backup is not available.');
        }
        $path = self::path($backup);
        if (!is_file($path) || !hash_equals((string) $backup['checksum'], (string) hash_file('sha256', $path))) {
            throw new RuntimeException('The backup file is missing or its checksum does not match. Nothing was changed.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The backup archive cannot be opened.');
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (!is_array($manifest) || (int) ($manifest['format'] ?? 0) !== self::FORMAT) {
            throw new RuntimeException('This is not a backup made by this application.');
        }
        $safety = self::create('FULL', 'SAFETY', $userId, 'Before restoring #' . $backupId);
        self::lock('restore', 3600);
        $restoreId = DB::insert('backup_restores', ['backup_id' => $backupId, 'safety_backup_id' => $safety, 'restored_by_id' => $userId]);
        @set_time_limit(0);
        try {
            if ($zip->locateName('database.sql') !== false) {
                $stream = $zip->getStream('database.sql');
                $pdo = DB::pdo();
                $statement = '';
                while (($line = fgets($stream)) !== false) {
                    if ($statement === '' && (str_starts_with($line, '--') || trim($line) === '')) {
                        continue;
                    }
                    $statement .= $line;
                    if (str_ends_with(rtrim($line, "\r\n"), ';')) {
                        $pdo->exec($statement);
                        $statement = '';
                    }
                }
                fclose($stream);
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (!str_starts_with($name, 'media/')) {
                    continue;
                }
                $key = substr($name, 6);
                if (!preg_match(Storage::KEY_PATTERN, $key)) {
                    continue; // Never write outside the upload directory.
                }
                $target = rtrim((string) config('app.upload_dir'), '/') . '/' . $key;
                if (!is_dir(dirname($target))) {
                    mkdir(dirname($target), 0755, true);
                }
                copy('zip://' . $path . '#' . $name, $target);
            }
            $zip->close();
            DB::update('backup_restores', ['status' => 'COMPLETED', 'completed_at' => now()], ['id' => $restoreId]);
            Settings::flush();
            return $safety;
        } catch (Throwable $e) {
            DB::update('backup_restores', ['status' => 'FAILED', 'error' => mb_substr($e->getMessage(), 0, 2000), 'completed_at' => now()], ['id' => $restoreId]);
            throw new RuntimeException('Restore failed: ' . $e->getMessage() . ' The safety backup #' . $safety . ' holds the state from just before.');
        } finally {
            self::unlock('restore');
        }
    }

    /** Register an uploaded backup ZIP after validating it. */
    public static function import(array $file, int $userId): int
    {
        if ((int) ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The upload failed.');
        }
        $zip = new ZipArchive();
        if ($zip->open((string) $file['tmp_name']) !== true) {
            throw new RuntimeException('That is not a ZIP file.');
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $zip->close();
        if (!is_array($manifest) || (int) ($manifest['format'] ?? 0) !== self::FORMAT) {
            throw new RuntimeException('That ZIP is not a backup made by this application.');
        }
        $filename = 'backup-' . date('Ymd-His') . '-imported-' . bin2hex(random_bytes(4)) . '.zip';
        $path = self::dir() . '/' . $filename;
        if (!move_uploaded_file((string) $file['tmp_name'], $path) && !copy((string) $file['tmp_name'], $path)) {
            throw new RuntimeException('Could not store the upload.');
        }
        return DB::insert('backups', [
            'filename' => $filename, 'type' => in_array($manifest['type'] ?? '', ['FULL', 'DATABASE', 'MEDIA'], true) ? $manifest['type'] : 'FULL',
            'status' => 'COMPLETED', 'origin' => 'IMPORTED', 'size' => filesize($path), 'checksum' => hash_file('sha256', $path),
            'tables_count' => (int) ($manifest['tables'] ?? 0), 'rows_count' => (int) ($manifest['rows'] ?? 0), 'media_count' => (int) ($manifest['media'] ?? 0),
            'created_by_id' => $userId, 'started_at' => now(), 'completed_at' => now(), 'note' => 'Imported, originally from ' . ($manifest['created_at'] ?? 'unknown date'),
        ]);
    }

    public static function delete(array $backup): void
    {
        try {
            @unlink(self::path($backup));
        } catch (RuntimeException) {
        }
        DB::delete('backups', ['id' => $backup['id']]);
    }

    /** Cron: take the scheduled backup when it is due and apply retention. */
    public static function runScheduled(): string
    {
        $s = Settings::group('backup');
        if (!$s['enabled']) {
            return 'not scheduled';
        }
        $last = DB::value("SELECT MAX(created_at) FROM backups WHERE origin = 'SCHEDULED' AND status = 'COMPLETED'");
        $interval = $s['frequency'] === 'weekly' ? 7 * 86400 : 86400;
        if ($last && strtotime((string) $last) > time() - $interval + 900) {
            return 'not due';
        }
        if ((int) date('G') < (int) $s['hour']) {
            return 'waiting for ' . (int) $s['hour'] . ':00';
        }
        $id = self::create($s['include_media'] ? 'FULL' : 'DATABASE', 'SCHEDULED', null);
        $keep = max(1, (int) $s['retention']);
        foreach (DB::all("SELECT * FROM backups WHERE origin = 'SCHEDULED' AND status = 'COMPLETED' ORDER BY created_at DESC LIMIT 1000 OFFSET $keep") as $old) {
            self::delete($old);
        }
        return "backup #$id taken";
    }

    public static function restoreInProgress(): bool
    {
        try {
            return (bool) DB::value("SELECT 1 FROM system_locks WHERE name = 'restore' AND expires_at > NOW()");
        } catch (Throwable) {
            return false;
        }
    }

    private static function lock(string $name, int $ttl): void
    {
        DB::query('DELETE FROM system_locks WHERE name = ? AND expires_at < NOW()', [$name]);
        try {
            DB::insert('system_locks', ['name' => $name, 'owner' => bin2hex(random_bytes(8)), 'expires_at' => date('Y-m-d H:i:s', time() + $ttl)]);
        } catch (\PDOException) {
            throw new RuntimeException(ucfirst($name) . ' already running. Try again when it finishes.');
        }
    }

    private static function unlock(string $name): void
    {
        DB::delete('system_locks', ['name' => $name]);
    }
}
