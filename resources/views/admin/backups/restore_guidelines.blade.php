@extends('layouts.app')

@section('title', __('erp.backups.restore_guidelines'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Breadcrumb / Back -->
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.backups.index') }}" class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Backups List</span>
        </a>
    </div>

    <!-- Header & Alert -->
    <div class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-xl p-5 shadow-sm">
        <div class="flex items-start gap-3">
            <i data-lucide="shield-alert" class="w-6 h-6 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5"></i>
            <div>
                <h1 class="text-base font-bold text-rose-900 dark:text-rose-200">
                    Disaster Recovery Safety Protocol & Restore Guidelines
                </h1>
                <p class="text-xs text-rose-700 dark:text-rose-300 mt-1 leading-relaxed">
                    Automated, unassisted "1-click" live database restores from a web UI are intentionally disabled for accounting integrity. Restoring a database drops and recreates tables, completely replacing current transactions, customer balances, invoices, and journal entries. Restorations must only be performed by authorized systems administrators with proper verification.
                </p>
            </div>
        </div>
    </div>

    <!-- Integrity Verification Widget -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm space-y-4">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500"></i>
            <span>Verify Backup Integrity & Checksum</span>
        </h2>

        <form method="GET" action="{{ route('admin.backups.restore-guidelines') }}" class="flex flex-col sm:flex-row gap-3">
            <select name="backup_id" class="flex-1 text-sm rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
                <option value="">Select a completed backup to verify...</option>
                @foreach($completedBackups as $b)
                    <option value="{{ $b->id }}" {{ ($selectedBackup?->id == $b->id) ? 'selected' : '' }}>
                        {{ $b->filename }} ({{ $b->formatted_size }} — {{ $b->created_at->format('Y-m-d H:i') }})
                    </option>
                @endforeach
            </select>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-5 py-2 text-xs font-semibold flex items-center justify-center gap-2 transition">
                <i data-lucide="search" class="w-4 h-4"></i>
                <span>Verify Checksum</span>
            </button>
        </form>

        @if($selectedBackup && $integrityCheck)
            <div class="mt-4 p-4 rounded-xl border {{ $integrityCheck['is_valid'] ? 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200' : 'bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-800 text-rose-900 dark:text-rose-200' }} text-xs space-y-2">
                <div class="flex items-center gap-2 font-bold">
                    <i data-lucide="{{ $integrityCheck['is_valid'] ? 'check' : 'x' }}" class="w-4 h-4"></i>
                    <span>{{ $integrityCheck['message'] }}</span>
                </div>
                <div class="font-mono text-[11px] space-y-1">
                    <div>Expected SHA-256: <span class="select-all">{{ $selectedBackup->checksum_sha256 }}</span></div>
                    <div>Actual File Hash: <span class="select-all">{{ $integrityCheck['checksum'] ?? 'N/A' }}</span></div>
                    <div>Physical Location: <span class="select-all">{{ $selectedBackup->filepath }}</span></div>
                </div>
            </div>
        @endif
    </div>

    <!-- Step-by-Step CLI Restoration Guide -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm space-y-6">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
            <i data-lucide="terminal" class="w-4 h-4 text-indigo-500"></i>
            <span>Command-Line Manual Restoration Instructions</span>
        </h2>

        <!-- Step 1 -->
        <div class="space-y-2">
            <h3 class="text-xs font-bold uppercase text-slate-700 dark:text-slate-300">
                Step 1: Put Application in Maintenance Mode
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Prevent incoming HTTP requests from writing new data while restoring:</p>
            <pre class="bg-slate-900 text-slate-200 p-3 rounded-lg text-xs font-mono"><code>php artisan down --message="System maintenance in progress. Please wait."</code></pre>
        </div>

        <!-- Step 2 -->
        <div class="space-y-2">
            <h3 class="text-xs font-bold uppercase text-slate-700 dark:text-slate-300">
                Step 2: Create a Safety Snapshot of Current State
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Always capture a pre-restore snapshot so you can revert if needed:</p>
            <pre class="bg-slate-900 text-slate-200 p-3 rounded-lg text-xs font-mono"><code>C:\xampp\mysql\bin\mysqldump.exe -u root -p fashionking > pre_restore_safety_backup.sql</code></pre>
        </div>

        <!-- Step 3 -->
        <div class="space-y-2">
            <h3 class="text-xs font-bold uppercase text-slate-700 dark:text-slate-300">
                Step 3: Execute MySQL / MariaDB Restore
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Stream the verified backup SQL dump back into the database:</p>
            @php
                $examplePath = $selectedBackup ? $selectedBackup->filepath : 'storage\app\backups\khalid_traders_db_YYYY_MM_DD_UUID.sql';
            @endphp
            <div class="space-y-2">
                <span class="text-[11px] text-slate-400 font-semibold">Windows PowerShell / CMD:</span>
                <pre class="bg-slate-900 text-emerald-400 p-3 rounded-lg text-xs font-mono"><code>C:\xampp\mysql\bin\mysql.exe -u root -p fashionking &lt; "{{ $examplePath }}"</code></pre>
            </div>
            <div class="space-y-2 mt-2">
                <span class="text-[11px] text-slate-400 font-semibold">Linux Server (Bash):</span>
                <pre class="bg-slate-900 text-emerald-400 p-3 rounded-lg text-xs font-mono"><code>mysql -u fashionking -p fashionking &lt; "{{ $examplePath }}"</code></pre>
            </div>
        </div>

        <!-- Step 4 -->
        <div class="space-y-2">
            <h3 class="text-xs font-bold uppercase text-slate-700 dark:text-slate-300">
                Step 4: Clear Application Caches and Bring App Online
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Ensure settings and schema caches are refreshed:</p>
            <pre class="bg-slate-900 text-slate-200 p-3 rounded-lg text-xs font-mono"><code>php artisan optimize:clear
php artisan up</code></pre>
        </div>
    </div>
</div>
@endsection
