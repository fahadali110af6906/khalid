@extends('layouts.app')

@section('title', __('erp.system_health.title'))

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
                <i data-lucide="activity" class="w-7 h-7 text-indigo-600 dark:text-indigo-400"></i>
                <span>{{ __('erp.system_health.title') }}</span>
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('erp.system_health.subtitle') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>System Operational</span>
            </span>
        </div>
    </div>

    <!-- Diagnostic Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Application Card -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-2">
                <i data-lucide="layers" class="w-5 h-5 text-indigo-500"></i>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('erp.system_health.application') }}</h2>
            </div>
            <div class="p-6 divide-y divide-slate-100 dark:divide-slate-700/60 text-xs">
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">Application Name</span>
                    <span class="font-bold text-slate-900 dark:text-white">{{ $diagnostics['application']['name'] }}</span>
                </div>
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">Environment</span>
                    <span class="font-mono uppercase font-bold text-slate-900 dark:text-white">{{ $diagnostics['application']['env'] }}</span>
                </div>
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">Debug Mode</span>
                    <span class="font-bold {{ $diagnostics['application']['debug'] ? 'text-amber-500' : 'text-emerald-600' }}">
                        {{ $diagnostics['application']['debug'] ? 'Enabled (Development)' : 'Disabled (Production Safe)' }}
                    </span>
                </div>
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">System Timezone</span>
                    <span class="font-mono text-slate-900 dark:text-white">{{ $diagnostics['application']['timezone'] }}</span>
                </div>
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">Active Locale</span>
                    <span class="font-mono font-bold uppercase text-slate-900 dark:text-white">{{ $diagnostics['application']['locale'] }}</span>
                </div>
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">Laravel Framework Version</span>
                    <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">v{{ $diagnostics['application']['laravel_version'] }}</span>
                </div>
            </div>
        </div>

        <!-- Runtime & Environment Card -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-2">
                <i data-lucide="cpu" class="w-5 h-5 text-indigo-500"></i>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('erp.system_health.environment') }}</h2>
            </div>
            <div class="p-6 divide-y divide-slate-100 dark:divide-slate-700/60 text-xs">
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">PHP Version</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-white">v{{ $diagnostics['environment']['php_version'] }}</span>
                </div>
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">PHP Memory Limit</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $diagnostics['environment']['memory_limit'] }}</span>
                </div>
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">Max Execution Time</span>
                    <span class="font-mono text-slate-900 dark:text-white">{{ $diagnostics['environment']['max_execution_time'] }}</span>
                </div>
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">Max Upload File Size</span>
                    <span class="font-mono text-slate-900 dark:text-white">{{ $diagnostics['environment']['upload_max_filesize'] }}</span>
                </div>
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">BCMath Precision Extension</span>
                    <span class="font-bold {{ $diagnostics['environment']['bcmath_loaded'] ? 'text-emerald-600' : 'text-rose-500' }}">
                        {{ $diagnostics['environment']['bcmath_loaded'] ? 'Loaded (High Precision Accounting)' : 'Missing' }}
                    </span>
                </div>
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">PDO Database Extension</span>
                    <span class="font-bold {{ $diagnostics['environment']['pdo_loaded'] ? 'text-emerald-600' : 'text-rose-500' }}">
                        {{ $diagnostics['environment']['pdo_loaded'] ? 'Loaded' : 'Missing' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Database Performance & Size -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-2">
                <i data-lucide="database" class="w-5 h-5 text-indigo-500"></i>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('erp.system_health.database') }}</h2>
            </div>
            <div class="p-6 divide-y divide-slate-100 dark:divide-slate-700/60 text-xs">
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">Default Connection</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $diagnostics['database']['connection'] }}</span>
                </div>
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">Driver</span>
                    <span class="font-mono font-bold uppercase text-slate-900 dark:text-white">{{ $diagnostics['database']['driver'] }}</span>
                </div>
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">Database Engine Status</span>
                    <span class="font-bold text-emerald-600 flex items-center gap-1">
                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                        <span>{{ ucfirst($diagnostics['database']['status']) }}</span>
                    </span>
                </div>
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">Engine Version</span>
                    <span class="font-mono text-slate-900 dark:text-white">{{ $diagnostics['database']['version'] }}</span>
                </div>
                <div class="py-2.5 flex justify-between items-center">
                    <span class="text-slate-500">Estimated Database Size</span>
                    <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ $diagnostics['database']['size_mb'] }} MB</span>
                </div>
            </div>
        </div>

        <!-- Storage Directory Permissions -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-2">
                <i data-lucide="folder-check" class="w-5 h-5 text-indigo-500"></i>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('erp.system_health.storage') }}</h2>
            </div>
            <div class="p-6 divide-y divide-slate-100 dark:divide-slate-700/60 text-xs">
                @foreach($diagnostics['storage'] as $name => $info)
                    <div class="py-2.5 flex justify-between items-center">
                        <div>
                            <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ $name }}</span>
                            <span class="block text-[10px] text-slate-400 max-w-xs truncate" title="{{ $info['path'] }}">{{ $info['path'] }}</span>
                        </div>
                        <span class="font-bold {{ $info['is_writable'] ? 'text-emerald-600' : 'text-rose-600' }} flex items-center gap-1">
                            <i data-lucide="{{ $info['is_writable'] ? 'check' : 'x' }}" class="w-4 h-4"></i>
                            <span>{{ $info['is_writable'] ? __('erp.system_health.healthy') : __('erp.system_health.warning') }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    <!-- System Stats Summary -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
            <i data-lucide="bar-chart-2" class="w-4 h-4 text-indigo-500"></i>
            <span>{{ __('erp.system_health.statistics') }}</span>
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-700/40">
                <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $diagnostics['users']['total'] }}</div>
                <div class="text-xs text-slate-500 mt-1">Total Users ({{ $diagnostics['users']['active'] }} Active)</div>
            </div>
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-700/40">
                <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $diagnostics['backups']['completed_count'] }}</div>
                <div class="text-xs text-slate-500 mt-1">Completed Backups</div>
            </div>
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-700/40">
                <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $diagnostics['audit']['total_count'] }}</div>
                <div class="text-xs text-slate-500 mt-1">Total Audit Trail Logs</div>
            </div>
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-700/40">
                <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400">{{ $diagnostics['audit']['recent_24h'] }}</div>
                <div class="text-xs text-slate-500 mt-1">Audit Events (Last 24h)</div>
            </div>
        </div>
    </div>
</div>
@endsection
