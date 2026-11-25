<?php

namespace App\Services\Administration;

use App\Models\BusinessSetting;
use App\Models\DatabaseBackup;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Storage directory for database backups (outside public web directory).
     */
    public function getBackupDirectory(): string
    {
        $dir = storage_path('app/backups');
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0750, true);
        }

        return $dir;
    }

    /**
     * Create a new database backup.
     */
    public function createBackup(?User $user = null): DatabaseBackup
    {
        $lock = Cache::lock('erp_backup_lock', 180);

        if (!$lock->get()) {
            throw new RuntimeException('Another backup operation is currently in progress. Please wait.');
        }

        $uuid = (string) Str::uuid();
        $timestamp = now()->format('Y_m_d_His');
        $filename = "khalid_traders_db_{$timestamp}_{$uuid}.sql";
        $dir = $this->getBackupDirectory();
        $filepath = $dir . DIRECTORY_SEPARATOR . $filename;

        $connection = config('database.default', 'mariadb');
        $driver = config("database.connections.{$connection}.driver", 'mysql');

        $backup = DatabaseBackup::create([
            'uuid' => $uuid,
            'filename' => $filename,
            'disk' => 'local',
            'filepath' => $filepath,
            'size_bytes' => 0,
            'type' => 'database',
            'driver' => $driver,
            'status' => 'pending',
            'created_by' => $user?->id,
        ]);

        try {
            if ($driver === 'sqlite') {
                $this->dumpSqlite($filepath);
            } else {
                // Try CLI mysqldump if available, otherwise pure PHP PDO fallback
                $cliDumped = $this->tryCliMysqldump($filepath, $connection);
                if (!$cliDumped) {
                    $this->dumpViaPdo($filepath);
                }
            }

            if (!File::exists($filepath) || File::size($filepath) === 0) {
                throw new RuntimeException('Backup output file is missing or empty.');
            }

            $size = File::size($filepath);
            $checksum = hash_file('sha256', $filepath);

            $backup->update([
                'status' => 'completed',
                'size_bytes' => $size,
                'checksum_sha256' => $checksum,
            ]);

            // Audit log
            $this->auditService->log(
                action: 'backup_create',
                model: $backup,
                description: "Created database backup: {$filename} ({$backup->formatted_size})",
                actor: $user
            );

            // Prune older backups according to retention policy
            $retentionCount = (int) BusinessSetting::get('backup_retention_count', 10);
            $this->pruneOldBackups($retentionCount);

            return $backup;
        } catch (Exception $e) {
            if (File::exists($filepath)) {
                File::delete($filepath);
            }

            $backup->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->auditService->log(
                action: 'backup_failed',
                model: $backup,
                description: "Database backup failed: " . $e->getMessage(),
                actor: $user
            );

            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * Try dumping MySQL / MariaDB via CLI executable.
     */
    protected function tryCliMysqldump(string $filepath, string $connection): bool
    {
        $mysqldumpPath = $this->findMysqldumpPath();
        if (!$mysqldumpPath) {
            return false;
        }

        $config = config("database.connections.{$connection}");
        if (!$config) {
            return false;
        }

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? '3306';
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        if (empty($database)) {
            return false;
        }

        // Build argument array for safe execution
        $cmd = [
            $mysqldumpPath,
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $username,
        ];

        if (!empty($password)) {
            $cmd[] = '--password=' . $password;
        }

        $cmd[] = '--single-transaction';
        $cmd[] = '--quick';
        $cmd[] = '--lock-tables=false';
        $cmd[] = '--result-file=' . $filepath;
        $cmd[] = $database;

        $commandLine = implode(' ', array_map('escapeshellarg', $cmd));

        $output = [];
        $returnCode = 0;
        @exec($commandLine, $output, $returnCode);

        return $returnCode === 0 && File::exists($filepath) && File::size($filepath) > 0;
    }

    /**
     * Locate mysqldump binary on system.
     */
    protected function findMysqldumpPath(): ?string
    {
        $standardPaths = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\Program Files\\MariaDB\\bin\\mariadb-dump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'mysqldump',
        ];

        foreach ($standardPaths as $path) {
            if (str_contains($path, '\\') && File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Pure PHP PDO streaming table dump for zero-dependency universal support.
     */
    protected function dumpViaPdo(string $filepath): void
    {
        $pdo = DB::connection()->getPdo();
        $handle = fopen($filepath, 'w');
        if (!$handle) {
            throw new RuntimeException("Cannot open backup destination file: {$filepath}");
        }

        $header = "-- Khalid Traders ERP Database Backup\n";
        $header .= "-- Generated at: " . now()->toIso8601String() . "\n";
        $header .= "-- Driver: " . DB::connection()->getDriverName() . "\n";
        $header .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        fwrite($handle, $header);

        $tablesStatement = $pdo->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'");
        $tables = $tablesStatement->fetchAll(PDO::FETCH_NUM);

        foreach ($tables as $tableRow) {
            $tableName = $tableRow[0];

            fwrite($handle, "\n-- --------------------------------------------------------\n");
            fwrite($handle, "-- Table structure for table `{$tableName}`\n");
            fwrite($handle, "-- --------------------------------------------------------\n\n");
            fwrite($handle, "DROP TABLE IF EXISTS `{$tableName}`;\n");

            $createTableStmt = $pdo->query("SHOW CREATE TABLE `{$tableName}`");
            $createTableRow = $createTableStmt->fetch(PDO::FETCH_ASSOC);
            $createTableSql = $createTableRow['Create Table'] ?? $createTableRow['Create View'] ?? '';
            fwrite($handle, $createTableSql . ";\n\n");

            // Dump Table Data in chunks
            $rowsCount = (int) $pdo->query("SELECT COUNT(*) FROM `{$tableName}`")->fetchColumn();
            if ($rowsCount > 0) {
                fwrite($handle, "-- Dumping data for table `{$tableName}`\n");

                $chunkSize = 250;
                $offset = 0;

                while ($offset < $rowsCount) {
                    $rowsStmt = $pdo->query("SELECT * FROM `{$tableName}` LIMIT {$chunkSize} OFFSET {$offset}");
                    $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($rows)) {
                        $columns = array_keys($rows[0]);
                        $escapedColumns = array_map(fn ($col) => "`{$col}`", $columns);
                        $colList = implode(', ', $escapedColumns);

                        $valuesSql = [];
                        foreach ($rows as $row) {
                            $escapedValues = array_map(function ($val) use ($pdo) {
                                if ($val === null) {
                                    return 'NULL';
                                }

                                return $pdo->quote($val);
                            }, $row);

                            $valuesSql[] = '(' . implode(', ', $escapedValues) . ')';
                        }

                        fwrite($handle, "INSERT INTO `{$tableName}` ({$colList}) VALUES\n" . implode(",\n", $valuesSql) . ";\n");
                    }

                    $offset += $chunkSize;
                }
                fwrite($handle, "\n");
            }
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fwrite($handle, "-- End of backup\n");
        fclose($handle);
    }

    /**
     * Dump SQLite database for local test suite and development.
     */
    protected function dumpSqlite(string $filepath): void
    {
        $dbPath = config('database.connections.sqlite.database');

        $handle = fopen($filepath, 'w');
        if (!$handle) {
            throw new RuntimeException("Cannot open backup destination file: {$filepath}");
        }

        fwrite($handle, "-- Khalid Traders ERP SQLite Backup\n");
        fwrite($handle, "-- Generated at: " . now()->toIso8601String() . "\n\n");

        if ($dbPath !== ':memory:' && File::exists($dbPath)) {
            // Read schema from sqlite_master
            $pdo = DB::connection()->getPdo();
            $tables = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $createSql) {
                fwrite($handle, $createSql . ";\n\n");
            }
        } else {
            // Memory SQLite schema dump
            $pdo = DB::connection()->getPdo();
            $tables = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tables as $tbl) {
                if ($tbl['sql']) {
                    fwrite($handle, $tbl['sql'] . ";\n\n");
                }
            }
        }

        fwrite($handle, "-- End of backup\n");
        fclose($handle);
    }

    /**
     * Verify backup file integrity and validity.
     */
    public function verifyBackupIntegrity(DatabaseBackup $backup): array
    {
        if (!$backup->fileExists()) {
            return [
                'is_valid' => false,
                'message' => 'Backup physical file not found on disk.',
            ];
        }

        $currentChecksum = hash_file('sha256', $backup->filepath);
        if ($backup->checksum_sha256 && $currentChecksum !== $backup->checksum_sha256) {
            return [
                'is_valid' => false,
                'message' => 'Checksum mismatch: file may have been altered or corrupted.',
            ];
        }

        // Check header content
        $contentSample = File::get($backup->filepath, false, null, 0, 500);
        $hasSqlSignature = str_contains($contentSample, '-- Khalid Traders ERP') || str_contains($contentSample, 'CREATE TABLE') || str_contains($contentSample, 'SET FOREIGN_KEY_CHECKS');

        return [
            'is_valid' => $hasSqlSignature,
            'message' => $hasSqlSignature ? 'Backup file verified and structurally intact.' : 'Unrecognized backup file format.',
            'size_bytes' => File::size($backup->filepath),
            'checksum' => $currentChecksum,
        ];
    }

    /**
     * Safe download response with authorization and audit logging.
     */
    public function downloadBackup(DatabaseBackup $backup, User $user): BinaryFileResponse
    {
        if (!$backup->fileExists()) {
            throw new RuntimeException("Backup file does not exist on disk.");
        }

        $this->auditService->log(
            action: 'backup_download',
            model: $backup,
            description: "Authorized download of database backup: {$backup->filename}",
            actor: $user
        );

        return response()->download($backup->filepath, $backup->filename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    /**
     * Prune older backups according to retention limit.
     */
    public function pruneOldBackups(int $keepCount = 10): int
    {
        $backups = DatabaseBackup::where('status', 'completed')
            ->orderByDesc('created_at')
            ->get();

        if ($backups->count() <= $keepCount) {
            return 0;
        }

        $toPrune = $backups->slice($keepCount);
        $pruned = 0;

        foreach ($toPrune as $oldBackup) {
            $oldBackup->delete(); // Automatically deletes physical file via model boot hook
            $pruned++;
        }

        return $pruned;
    }
}
