<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Writes the whole database to a gzipped .sql file. Done in PHP rather than
 * with mysqldump so it needs nothing extra on the server, and row by row so a
 * large table does not fill the small server's memory.
 *
 * The file can be loaded back with the mysql command line; see the README.
 */
class DatabaseBackupService
{
    /** Separate connection, so its session settings never touch the rest of the request. */
    private const string CONNECTION = 'database_backup';

    /**
     * Their structure is kept but not their rows: live login sessions should
     * not sit in a downloaded file, and all three refill by themselves.
     */
    private const array TABLES_WITHOUT_ROWS = ['sessions', 'cache', 'cache_locks'];

    private const int BATCH_ROWS = 200;

    private const int BATCH_BYTES = 512 * 1024;

    private const int LEFTOVER_MINUTES = 60;

    /**
     * @return string path of the finished file; the caller deletes it
     *
     * @throws Throwable when the backup failed; no file is left behind
     */
    public function create(): string
    {
        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory, 0700);
        $this->removeLeftovers($directory);

        $path = $directory.DIRECTORY_SEPARATOR.Str::random(40).'.sql.gz';

        try {
            $this->dump($path);
        } catch (Throwable $e) {
            File::delete($path);

            throw $e;
        } finally {
            DB::purge(self::CONNECTION);
        }

        return $path;
    }

    /** A download cut short can leave its file behind; customer data must not pile up. */
    private function removeLeftovers(string $directory): void
    {
        foreach (File::glob($directory.DIRECTORY_SEPARATOR.'*.sql.gz') as $file) {
            if (File::lastModified($file) < time() - self::LEFTOVER_MINUTES * 60) {
                File::delete($file);
            }
        }
    }

    private function dump(string $path): void
    {
        $pdo = $this->connect();

        // Times in UTC so a restore on a server in another time zone keeps
        // them. One snapshot, so a booking made meanwhile cannot appear in
        // only some of the tables.
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

        $file = gzopen($path, 'wb6');

        if ($file === false) {
            throw new RuntimeException('Could not open the backup file for writing.');
        }

        chmod($path, 0600);

        try {
            $this->write($file, implode("\n", [
                '-- '.config('app.name').' database backup, created '.now()->toDateTimeString().' UTC',
                'SET NAMES utf8mb4;',
                "SET time_zone = '+00:00';",
                "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';",
                'SET FOREIGN_KEY_CHECKS = 0;',
                'SET UNIQUE_CHECKS = 0;',
                '',
                '',
            ]));

            $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN, 0);

            foreach ($tables as $table) {
                $this->dumpTable($pdo, $file, $table);
            }

            $this->write($file, "SET UNIQUE_CHECKS = 1;\nSET FOREIGN_KEY_CHECKS = 1;\n-- Backup complete\n");
        } finally {
            gzclose($file);
        }
    }

    private function connect(): PDO
    {
        $config = config('database.connections.'.config('database.default'));

        if (! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Database backups only support MySQL and MariaDB.');
        }

        // Every value as MySQL's own text, so no number loses precision on its way through PHP.
        $config['options'] = [PDO::ATTR_STRINGIFY_FETCHES => true] + ($config['options'] ?? []);
        config(['database.connections.'.self::CONNECTION => $config]);

        return DB::connection(self::CONNECTION)->getPdo();
    }

    /** @param resource $file */
    private function dumpTable(PDO $pdo, $file, string $table): void
    {
        $name = $this->identifier($table);
        $createTable = $pdo->query("SHOW CREATE TABLE {$name}")->fetch(PDO::FETCH_NUM)[1];

        $this->write($file, "DROP TABLE IF EXISTS {$name};\n{$createTable};\n\n");

        if (in_array($table, self::TABLES_WITHOUT_ROWS, true)) {
            return;
        }

        // Computed columns refuse values. Match the whole phrase: plain
        // timestamps also say DEFAULT_GENERATED here.
        $columns = array_values(array_filter(
            $pdo->query("SHOW COLUMNS FROM {$name}")->fetchAll(PDO::FETCH_ASSOC),
            fn (array $column): bool => preg_match('/(VIRTUAL|STORED) GENERATED/i', $column['Extra']) !== 1,
        ));
        $isBinary = array_map(fn (array $column): bool => preg_match('/blob|binary|^bit/i', $column['Type']) === 1, $columns);
        $columnList = implode(', ', array_map(fn (array $column): string => $this->identifier($column['Field']), $columns));

        $insert = "INSERT INTO {$name} ({$columnList}) VALUES\n";
        $rows = [];
        $bytes = 0;

        // Unbuffered: rows arrive one at a time instead of all into memory.
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $statement = $pdo->query("SELECT {$columnList} FROM {$name}");

        try {
            while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
                $values = [];

                foreach ($row as $index => $value) {
                    $values[] = $this->literal($pdo, $value, $isBinary[$index]);
                }

                $line = '('.implode(', ', $values).')';
                $rows[] = $line;
                $bytes += strlen($line);

                if (count($rows) >= self::BATCH_ROWS || $bytes >= self::BATCH_BYTES) {
                    $this->write($file, $insert.implode(",\n", $rows).";\n");
                    $rows = [];
                    $bytes = 0;
                }
            }
        } finally {
            $statement->closeCursor();
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }

        if ($rows !== []) {
            $this->write($file, $insert.implode(",\n", $rows).";\n");
        }

        $this->write($file, "\n");
    }

    private function literal(PDO $pdo, ?string $value, bool $isBinary): string
    {
        return match (true) {
            $value === null => 'NULL',
            $isBinary && $value !== '' => '0x'.bin2hex($value),
            default => $pdo->quote($value),
        };
    }

    private function identifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    /** @param resource $file */
    private function write($file, string $sql): void
    {
        if (gzwrite($file, $sql) === false) {
            throw new RuntimeException('Could not write the backup file. Is the disk full?');
        }
    }
}
