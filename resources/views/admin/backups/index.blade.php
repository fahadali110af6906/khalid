@extends('layouts.app')

@section('title', __('erp.backups.title'))

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
                <i data-lucide="database" class="w-7 h-7 text-indigo-600 dark:text-indigo-400"></i>
                <span>{{ __('erp.backups.title') }}</span>
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('erp.backups.subtitle') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.backups.restore-guidelines') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold shadow-sm transition">
                <i data-lucide="life-buoy" class="w-4 h-4 text-amber-500"></i>
                <span>{{ __('erp.backups.restore_guidelines') }}</span>
            </a>

            <form action="{{ route('admin.backups.create') }}" method="POST" class="m-0" onsubmit="return confirm('Initiate database backup dump now?')">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-md transition">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    <span>{{ __('erp.backups.create_backup') }}</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Alert / Safety Notice -->
    <div class="bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-xl p-4 flex items-start gap-3 text-xs text-amber-800 dark:text-amber-200">
        <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5"></i>
        <div>
            <h4 class="font-bold">{{ __('erp.backups.warning_title') }}</h4>
            <p class="mt-0.5 text-amber-700 dark:text-amber-300">{{ __('erp.backups.warning_body') }}</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
            <div class="flex items-center justify-between text-slate-400">
                <span class="text-xs font-bold uppercase tracking-wider">Completed Backups</span>
                <i data-lucide="archive" class="w-5 h-5 text-indigo-500"></i>
            </div>
            <div class="text-2xl font-black text-slate-900 dark:text-white mt-2">{{ $stats['total_count'] }}</div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
            <div class="flex items-center justify-between text-slate-400">
                <span class="text-xs font-bold uppercase tracking-wider">Total Storage</span>
                <i data-lucide="hard-drive" class="w-5 h-5 text-emerald-500"></i>
            </div>
            <div class="text-2xl font-black text-slate-900 dark:text-white mt-2 font-mono">
                {{ number_format($stats['total_size'] / 1024 / 1024, 2) }} MB
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
            <div class="flex items-center justify-between text-slate-400">
                <span class="text-xs font-bold uppercase tracking-wider">Latest Backup</span>
                <i data-lucide="clock" class="w-5 h-5 text-purple-500"></i>
            </div>
            <div class="text-sm font-bold text-slate-900 dark:text-white mt-2">
                {{ $stats['latest'] ? $stats['latest']->created_at->diffForHumans() : 'None' }}
            </div>
        </div>
    </div>

    <!-- Backups Table -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-4">{{ __('erp.backups.filename') }}</th>
                        <th class="py-3 px-4">{{ __('erp.backups.size') }}</th>
                        <th class="py-3 px-4">{{ __('erp.backups.status') }}</th>
                        <th class="py-3 px-4">{{ __('erp.backups.checksum') }}</th>
                        <th class="py-3 px-4">{{ __('erp.backups.created_at') }}</th>
                        <th class="py-3 px-4">{{ __('erp.backups.created_by') }}</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 font-medium">
                    @forelse($backups as $b)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <i data-lucide="file-code" class="w-4 h-4 text-indigo-500"></i>
                                <span>{{ $b->filename }}</span>
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-600 dark:text-slate-300">
                                {{ $b->formatted_size }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold
                                    @if($b->status === 'completed') bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300
                                    @elseif($b->status === 'failed') bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300
                                    @else bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 @endif">
                                    {{ ucfirst($b->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-mono text-[10px] text-slate-400 max-w-[150px] truncate" title="{{ $b->checksum_sha256 }}">
                                {{ $b->checksum_sha256 ? substr($b->checksum_sha256, 0, 16) . '...' : '—' }}
                            </td>
                            <td class="py-3 px-4 text-slate-600 dark:text-slate-300 font-mono text-[11px]">
                                {{ $b->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                                {{ $b->creator?->name ?? 'System' }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if($b->status === 'completed')
                                        <a href="{{ route('admin.backups.download', $b) }}" class="p-1.5 rounded-lg text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition" title="{{ __('erp.backups.download') }}">
                                            <i data-lucide="download" class="w-4 h-4"></i>
                                        </a>
                                        <a href="{{ route('admin.backups.restore-guidelines', ['backup_id' => $b->id]) }}" class="p-1.5 rounded-lg text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/30 transition" title="Inspect / Verify">
                                            <i data-lucide="search" class="w-4 h-4"></i>
                                        </a>
                                    @endif
                                    <form action="{{ route('admin.backups.destroy', $b) }}" method="POST" class="m-0" onsubmit="return confirm('Delete this backup permanently?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition" title="{{ __('erp.backups.delete') }}">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400 text-xs">
                                {{ __('erp.backups.no_backups') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($backups->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                {{ $backups->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
