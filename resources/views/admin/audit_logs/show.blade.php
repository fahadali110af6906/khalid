@extends('layouts.app')

@section('title', __('erp.audit_logs.title') . ' #' . $auditLog->id)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Breadcrumb / Back -->
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.audit-logs.index') }}" class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Audit Trail</span>
        </a>
        <span class="text-xs font-mono text-slate-400">ID: {{ $auditLog->id }}</span>
    </div>

    <!-- Main Card -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider
                    @if(str_contains($auditLog->action, 'delete') || str_contains($auditLog->action, 'fail'))
                        bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300
                    @elseif(str_contains($auditLog->action, 'update'))
                        bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300
                    @else
                        bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300
                    @endif">
                    {{ $auditLog->action }}
                </span>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white mt-2">{{ $auditLog->description }}</h1>
            </div>
            <div class="text-right text-xs text-slate-500 font-mono">
                <div>{{ $auditLog->created_at->format('Y-m-d H:i:s') }}</div>
                <div>({{ $auditLog->created_at->diffForHumans() }})</div>
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 border-b border-slate-200 dark:border-slate-700 text-xs">
            <div>
                <span class="block text-slate-400 uppercase font-semibold text-[10px]">{{ __('erp.audit_logs.actor') }}</span>
                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $auditLog->user?->name ?? 'System' }}</span>
                @if($auditLog->user)
                    <span class="block text-slate-400">{{ $auditLog->user->email }}</span>
                @endif
            </div>

            <div>
                <span class="block text-slate-400 uppercase font-semibold text-[10px]">{{ __('erp.audit_logs.model') }}</span>
                <span class="font-bold font-mono text-slate-800 dark:text-slate-200">
                    {{ $auditLog->model_type ? class_basename($auditLog->model_type) . ' #' . $auditLog->model_id : 'Global / None' }}
                </span>
            </div>

            <div>
                <span class="block text-slate-400 uppercase font-semibold text-[10px]">{{ __('erp.audit_logs.ip_address') }}</span>
                <span class="font-mono text-slate-800 dark:text-slate-200">{{ $auditLog->ip_address ?? '—' }}</span>
            </div>

            <div>
                <span class="block text-slate-400 uppercase font-semibold text-[10px]">Location Scope</span>
                <span class="font-medium text-slate-800 dark:text-slate-200">{{ $auditLog->location?->name ?? 'All Locations' }}</span>
            </div>
        </div>

        @if($auditLog->user_agent)
            <div class="px-6 py-3 bg-slate-50 dark:bg-slate-900/40 border-b border-slate-200 dark:border-slate-700 text-[11px] font-mono text-slate-500 truncate" title="{{ $auditLog->user_agent }}">
                User Agent: {{ $auditLog->user_agent }}
            </div>
        @endif

        <!-- Before / After Diff Inspection -->
        <div class="p-6 space-y-6">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                <i data-lucide="git-commit" class="w-4 h-4 text-indigo-500"></i>
                <span>Data Changes & State Diffs</span>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Previous State -->
                <div class="space-y-2">
                    <div class="text-xs font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i data-lucide="minus-circle" class="w-3.5 h-3.5"></i>
                        <span>{{ __('erp.audit_logs.old_value') }}</span>
                    </div>
                    <pre class="bg-slate-900 text-rose-200 p-4 rounded-xl text-xs font-mono overflow-x-auto border border-slate-800 min-h-[140px]"><code>{{ !empty($auditLog->old_values) ? json_encode($auditLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'null (No previous state)' }}</code></pre>
                </div>

                <!-- Updated State -->
                <div class="space-y-2">
                    <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                        <span>{{ __('erp.audit_logs.new_value') }}</span>
                    </div>
                    <pre class="bg-slate-900 text-emerald-200 p-4 rounded-xl text-xs font-mono overflow-x-auto border border-slate-800 min-h-[140px]"><code>{{ !empty($auditLog->new_values) ? json_encode($auditLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'null (No new state)' }}</code></pre>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
