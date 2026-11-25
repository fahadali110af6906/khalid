@extends('layouts.app')

@section('title', __('erp.users.title'))

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
                <i data-lucide="users" class="w-7 h-7 text-indigo-600 dark:text-indigo-400"></i>
                <span>{{ __('erp.users.title') }}</span>
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('erp.users.subtitle') }}
            </p>
        </div>

        <div>
            <a href="{{ route('admin.users.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-md transition">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                <span>{{ __('erp.users.add_user') }}</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Search Keyword</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, email, phone..."
                       class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Filter by Role</label>
                <select name="role_id" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
                    <option value="">All Roles</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ request('role_id') == $role->id ? 'selected' : '' }}>{{ $role->label ?? $role->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Account Status</label>
                <select name="status" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
                    <option value="">All Statuses</option>
                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active Only</option>
                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive Only</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg py-2 px-3 text-xs font-semibold flex items-center justify-center gap-1.5 transition">
                    <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                    <span>Apply Filter</span>
                </button>
                <a href="{{ route('admin.users.index') }}" class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-lg py-2 px-3 text-xs font-semibold flex items-center justify-center transition">
                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-4">{{ __('erp.users.name') }}</th>
                        <th class="py-3 px-4">{{ __('erp.users.role') }}</th>
                        <th class="py-3 px-4">{{ __('erp.users.phone') }}</th>
                        <th class="py-3 px-4">{{ __('erp.users.default_location') }}</th>
                        <th class="py-3 px-4">{{ __('erp.users.assigned_locations') }}</th>
                        <th class="py-3 px-4">{{ __('erp.users.status') }}</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 font-medium">
                    @forelse($users as $user)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition">
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $user->name }}</div>
                                <div class="text-[11px] text-slate-400">{{ $user->email }}</div>
                            </td>
                            <td class="py-3 px-4">
                                @forelse($user->roles as $r)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                        {{ $r->label ?? $r->name }}
                                    </span>
                                @empty
                                    <span class="text-slate-400 text-xs">No Role</span>
                                @endforelse
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-600 dark:text-slate-300">
                                {{ $user->phone ?? '—' }}
                            </td>
                            <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                                {{ $user->defaultLocation?->name ?? 'All / Unset' }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex flex-wrap gap-1 max-w-xs">
                                    @forelse($user->locations as $loc)
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                            {{ $loc->name }}
                                        </span>
                                    @empty
                                        <span class="text-slate-400 text-[11px]">Global / All</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold
                                    {{ $user->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300' }}">
                                    {{ $user->is_active ? __('erp.users.active') : __('erp.users.inactive') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="p-1.5 rounded-lg text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition" title="{{ __('erp.users.edit_user') }}">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </a>

                                    @if($user->id !== auth()->id())
                                        <form action="{{ route('admin.users.toggle-active', $user) }}" method="POST" class="m-0" onsubmit="return confirm('Toggle status for this user?')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="p-1.5 rounded-lg {{ $user->is_active ? 'text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/30' : 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/30' }} transition" title="{{ __('erp.users.toggle_status') }}">
                                                <i data-lucide="{{ $user->is_active ? 'user-x' : 'user-check' }}" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400 text-xs">
                                {{ __('erp.users.no_users') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
