<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /**
     * Display the paginated audit trail.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        if (!$user->isOwner()) {
            abort(403, 'Forbidden: Only authorized executive administrators can inspect audit trails.');
        }

        $action = $request->query('action');
        $userId = $request->query('user_id');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $search = $request->query('search');

        $query = AuditLog::with(['user', 'location'])->latest('created_at');

        if ($action) {
            $query->where('action', $action);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('auditable_type', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(25)->withQueryString();
        $users = User::orderBy('name')->get();
        $actions = AuditLog::select('action')->distinct()->orderBy('action')->pluck('action');

        return view('admin.audit_logs.index', compact('logs', 'users', 'actions', 'action', 'userId', 'fromDate', 'toDate', 'search'));
    }

    /**
     * Display a specific audit trail record.
     */
    public function show(Request $request, AuditLog $auditLog): View
    {
        $user = $request->user();
        if (!$user->isOwner()) {
            abort(403, 'Forbidden.');
        }

        $auditLog->load(['user', 'location']);

        return view('admin.audit_logs.show', compact('auditLog'));
    }
}
