@extends('layouts.app')

@section('title', __('erp.users.edit_user'))

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <!-- Breadcrumb / Back -->
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Users List</span>
        </a>
    </div>

    <!-- Form Card -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
            <h1 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="edit-3" class="w-5 h-5 text-indigo-600 dark:text-indigo-400"></i>
                <span>{{ __('erp.users.edit_user') }}: {{ $user->name }}</span>
            </h1>
        </div>

        <form action="{{ route('admin.users.update', $user) }}" method="POST" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.users.name') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3">
                    @error('name') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.users.email') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3">
                    @error('email') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.users.phone') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" required
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3">
                    @error('phone') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.users.password') }}
                    </label>
                    <input type="password" name="password" minlength="8" placeholder="Leave empty to keep current password"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3">
                    <span class="text-[11px] text-slate-400 mt-1 block">{{ __('erp.users.password_help') }}</span>
                    @error('password') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.users.role') }} <span class="text-rose-500">*</span>
                    </label>
                    @php
                        $userRoleId = $user->roles->first()?->id;
                    @endphp
                    <select name="role_id" required class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3">
                        <option value="">Select Role...</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" {{ old('role_id', $userRoleId) == $role->id ? 'selected' : '' }}>
                                {{ $role->label ?? $role->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('role_id') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.users.default_location') }}
                    </label>
                    <select name="default_location_id" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3">
                        <option value="">None (Global Access)</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ old('default_location_id', $user->default_location_id) == $loc->id ? 'selected' : '' }}>
                                {{ $loc->name }} ({{ ucfirst($loc->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Locations Multiselect / Checkboxes -->
            <div class="space-y-2 border-t border-slate-200 dark:border-slate-700 pt-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {{ __('erp.users.assigned_locations') }}
                </label>
                @php
                    $assignedLocIds = $user->locations->pluck('id')->toArray();
                @endphp
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($locations as $loc)
                        <label class="flex items-center gap-2 p-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-xs cursor-pointer">
                            <input type="checkbox" name="locations[]" value="{{ $loc->id }}"
                                   {{ (is_array(old('locations')) ? in_array($loc->id, old('locations')) : in_array($loc->id, $assignedLocIds)) ? 'checked' : '' }}
                                   class="rounded text-indigo-600 border-slate-300 dark:border-slate-600 focus:ring-indigo-500">
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ $loc->name }}</span>
                            <span class="text-[10px] text-slate-400">({{ ucfirst($loc->type) }})</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Active Toggle -->
            <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }}
                           {{ $user->id === auth()->id() ? 'disabled' : '' }}
                           class="rounded text-indigo-600 border-slate-300 dark:border-slate-600 focus:ring-indigo-500">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Account Active & Enabled for Login</span>
                    @if($user->id === auth()->id())
                        <span class="text-[11px] text-amber-500 font-normal">(Cannot deactivate self)</span>
                    @endif
                </label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                <a href="{{ route('admin.users.index') }}" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-md transition">
                    Update User
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
