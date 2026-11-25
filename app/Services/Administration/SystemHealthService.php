<?php

namespace App\Services\Administration;

use App\Models\AuditLog;
use App\Models\DatabaseBackup;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SystemHealthService
{
    /**
     * Collect system health diagnostics without exposing credentials.
     */
    public function getDiagnostics(): array
    {
        return [
            'application' => $this->getApplicationInfo(),
            'environment' => $this->getEnvironmentInfo(),
            'database' => $this->getDatabaseInfo(),
            'storage' => $this->getStoragePermissions(),
            'backups' => $this->getBackupStats(),
            'users' => $this->getUserStats(),
            'audit' => $this->getAuditStats(),
        ];
    }

    protected function getApplicationInfo(): array
    {
        return [
            'name' => config('app.name', 'Khalid Traders ERP'),
            'env' => config('app.env'),
            'debug' => config('app.debug'),
            'url' => config('app.url'),
            'timezone' => config('app.timezone', 'Asia/Karachi'),
            'locale' => app()->getLocale(),
            'laravel_version' => app()->version(),
        ];
    }

    protected function getEnvironmentInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'opcache_enabled' => function_exists('opcache_get_status') && !empty(opcache_get_status()),
            'bcmath_loaded' => extension_loaded('bcmath'),
            'pdo_loaded' => extension_loaded('pdo'),
        ];
    }

    protected function getDatabaseInfo(): array
    {
        $connectionName = config('database.default');
        $driver = config("database.connections.{$connectionName}.driver", 'unknown');

        $status = 'healthy';
        $version = 'unknown';
        $sizeMb = '0.00';

        try {
            $pdo = DB::connection()->getPdo();
            $version = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);

            if ($driver === 'mysql' || $driver === 'mariadb') {
                $dbName = config("database.connections.{$connectionName}.database");
                $sizeQuery = DB::select("
                    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                    FROM information_schema.tables 
                    WHERE table_schema = ?
                ", [$dbName]);

                $sizeMb = $sizeQuery[0]->size_mb ?? '0.00';
            }
        } catch (Exception $e) {
            $status = 'error: ' . $e->getMessage();
        }

        return [
            'connection' => $connectionName,
            'driver' => $driver,
            'status' => $status,
            'version' => $version,
            'size_mb' => $sizeMb,
        ];
    }

    protected function getStoragePermissions(): array
    {
        $paths = [
            'storage_app' => storage_path('app'),
            'storage_backups' => storage_path('app/backups'),
            'storage_logs' => storage_path('logs'),
            'storage_framework_views' => storage_path('framework/views'),
            'bootstrap_cache' => base_path('bootstrap/cache'),
        ];

        $results = [];
        foreach ($paths as $name => $path) {
            $exists = File::exists($path);
            $writable = $exists && File::isWritable($path);

            $results[$name] = [
                'path' => $path,
                'exists' => $exists,
                'is_writable' => $writable,
            ];
        }

        return $results;
    }

    protected function getBackupStats(): array
    {
        $total = DatabaseBackup::count();
        $completed = DatabaseBackup::where('status', 'completed')->count();
        $failed = DatabaseBackup::where('status', 'failed')->count();
        $lastBackup = DatabaseBackup::where('status', 'completed')->latest()->first();
        $totalBytes = DatabaseBackup::where('status', 'completed')->sum('size_bytes');

        $formattedBytes = $totalBytes >= 1048576
            ? number_format($totalBytes / 1048576, 2) . ' MB'
            : number_format($totalBytes / 1024, 2) . ' KB';

        return [
            'total_count' => $total,
            'completed_count' => $completed,
            'failed_count' => $failed,
            'last_backup_date' => $lastBackup?->created_at?->format('M d, Y h:i A'),
            'total_storage_used' => $formattedBytes,
        ];
    }

    protected function getUserStats(): array
    {
        return [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
        ];
    }

    protected function getAuditStats(): array
    {
        $total = AuditLog::count();
        $recent24h = AuditLog::where('created_at', '>=', now()->subDay())->count();
        $lastLog = AuditLog::latest('created_at')->first();

        return [
            'total_count' => $total,
            'total_entries' => $total,
            'recent_24h' => $recent24h,
            'last_entry_date' => $lastLog?->created_at?->format('M d, Y h:i A'),
            'last_action' => $lastLog?->action,
        ];
    }
}
