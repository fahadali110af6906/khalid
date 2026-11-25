<?php

namespace App\Http\Controllers;

use App\Models\DatabaseBackup;
use App\Services\Administration\AuditService;
use App\Services\Administration\BackupService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backupService,
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of database backups.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        if (!$user->isOwner()) {
            abort(403, 'Forbidden: Only business owners can manage system backups.');
        }

        $backups = DatabaseBackup::with('creator')
            ->orderByDesc('created_at')
            ->paginate(15);

        $stats = [
            'total_count' => DatabaseBackup::where('status', 'completed')->count(),
            'total_size' => DatabaseBackup::where('status', 'completed')->sum('size_bytes'),
            'latest' => DatabaseBackup::where('status', 'completed')->latest()->first(),
        ];

        return view('admin.backups.index', compact('backups', 'stats'));
    }

    /**
     * Trigger generation of a new database backup.
     */
    public function create(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isOwner()) {
            abort(403, 'Forbidden: Only business owners can create system backups.');
        }

        try {
            $backup = $this->backupService->createBackup($user);

            return redirect()->route('admin.backups.index')
                ->with('success', "Database backup created successfully: {$backup->filename} ({$backup->formatted_size})");
        } catch (Exception $e) {
            return redirect()->route('admin.backups.index')
                ->with('error', "Backup generation failed: {$e->getMessage()}");
        }
    }

    /**
     * Download a verified database backup securely.
     */
    public function download(Request $request, DatabaseBackup $backup): BinaryFileResponse
    {
        $user = $request->user();
        if (!$user->isOwner()) {
            abort(403, 'Forbidden: Only business owners can download backups.');
        }

        return $this->backupService->downloadBackup($backup, $user);
    }

    /**
     * Delete an existing backup file and record.
     */
    public function destroy(Request $request, DatabaseBackup $backup): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isOwner()) {
            abort(403, 'Forbidden: Only business owners can delete backups.');
        }

        $filename = $backup->filename;

        $this->auditService->log(
            action: 'backup_delete',
            model: $backup,
            description: "Deleted database backup: {$filename}",
            actor: $user
        );

        $backup->delete();

        return redirect()->route('admin.backups.index')
            ->with('success', "Database backup '{$filename}' was deleted successfully.");
    }

    /**
     * Show disaster recovery, integrity verification, and manual restore guidelines.
     */
    public function restoreGuidelines(Request $request): View
    {
        $user = $request->user();
        if (!$user->isOwner()) {
            abort(403, 'Forbidden: Only business owners can view recovery procedures.');
        }

        $backupId = $request->query('backup_id');
        $selectedBackup = null;
        $integrityCheck = null;

        if ($backupId) {
            $selectedBackup = DatabaseBackup::find($backupId);
            if ($selectedBackup) {
                $integrityCheck = $this->backupService->verifyBackupIntegrity($selectedBackup);
            }
        }

        $completedBackups = DatabaseBackup::where('status', 'completed')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.backups.restore_guidelines', compact('completedBackups', 'selectedBackup', 'integrityCheck'));
    }
}
