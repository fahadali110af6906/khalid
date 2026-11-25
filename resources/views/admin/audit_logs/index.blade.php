@extends('layouts.app')

@section('title', __('erp.audit_logs.title'))

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
                <i data-lucide="shield-check" class="w-7 h-7 text-indigo-600 dark:text-indigo-400"></i>
                <span>{{ __('erp.audit_logs.title') }}</span>
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('erp.audit_logs.subtitle') }}
            </p>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                    {{ __('erp.audit_logs.filter_action') }}
                </label>
                <select name="action" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
                    <option value="">All Actions</option>
                    @foreach($actions as $act)
                        <option value="{{ $act }}" {{ request('action') === $act ? 'selected' : '' }}>{{ $act }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                    {{ __('erp.audit_logs.filter_user') }}
                </label>
                <select name="user_id" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
                    <option value="">All Users</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                    {{ __('erp.audit_logs.filter_from') }}
                </label>
                <input type="date" name="from_date" value="{{ request('from_date') }}"
                       class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                    {{ __('erp.audit_logs.filter_to') }}
                </label>
                <input type="date" name="to_date" value="{{ request('to_date') }}"
                       class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg py-2 px-3 text-xs font-semibold flex items-center justify-center gap-1.5 transition">
                    <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                    <span>Apply Filter</span>
                </button>
                <a href="{{ route('admin.audit-logs.index') }}" class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-lg py-2 px-3 text-xs font-semibold flex items-center justify-center transition">
                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Audit Logs Table -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-4">#</th>
                        <th class="py-3 px-4">{{ __('erp.audit_logs.timestamp') }}</th>
                        <th class="py-3 px-4">{{ __('erp.audit_logs.actor') }}</th>
                        <th class="py-3 px-4">{{ __('erp.audit_logs.action') }}</th>
                        <th class="py-3 px-4">{{ __('erp.audit_logs.model') }}</th>
                        <th class="py-3 px-4">{{ __('erp.audit_logs.description') }}</th>
                        <th class="py-3 px-4">{{ __('erp.audit_logs.ip_address') }}</th>
                        <th class="py-3 px-4 text-right">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 font-medium">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition">
                            <td class="py-3 px-4 text-slate-400">{{ $log->id }}</td>
                            <td class="py-3 px-4 text-slate-600 dark:text-slate-300 font-mono">
                                {{ $log->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-bold text-slate-900 dark:text-white">{{ $log->user?->name ?? 'System' }}</span>
                                @if($log->location)
                                    <span class="block text-[10px] text-slate-400">{{ $log->location->name }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold
                                    @if(str_contains($log->action, 'delete') || str_contains($log->action, 'fail') || str_contains($log->action, 'reverse'))
                                        bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300
                                    @elseif(str_contains($log->action, 'update') || str_contains($log->action, 'toggle'))
                                        bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300
                                    @else
                                        bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300
                                    @endif">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-slate-600 dark:text-slate-300">
                                {{ $log->model_type ? class_basename($log->model_type) . ' #' . $log->model_id : '—' }}
                            </td>
                            <td class="py-3 px-4 text-slate-700 dark:text-slate-300 max-w-xs truncate" title="{{ $log->description }}">
                                {{ $log->description }}
                            </td>
                            <td class="py-3 px-4 text-slate-500 font-mono text-[11px]">
                                {{ $log->ip_address ?? '—' }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <a href="{{ route('admin.audit-logs.show', $log) }}" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-semibold text-xs">
                                    <span>{{ __('erp.audit_logs.view_diff') }}</span>
                                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400 text-xs">
                                {{ __('erp.audit_logs.no_logs') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
