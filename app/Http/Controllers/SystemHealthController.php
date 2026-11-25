<?php

namespace App\Http\Controllers;

use App\Services\Administration\SystemHealthService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    public function __construct(
        protected SystemHealthService $systemHealthService
    ) {}

    /**
     * Display system health diagnostics and operational status.
     */
    public function index(Request $request): View
    {
        $currentUser = $request->user();
        if (!$currentUser->isOwner()) {
            abort(403, 'Forbidden: Only business owners can view system diagnostics.');
        }

        $diagnostics = $this->systemHealthService->getDiagnostics();

        return view('admin.health.index', compact('diagnostics'));
    }
}
