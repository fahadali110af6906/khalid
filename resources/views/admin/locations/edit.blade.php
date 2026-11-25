@extends('layouts.app')

@section('title', __('erp.locations_management.edit_location'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Breadcrumb & Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2 text-xs text-slate-500">
            <a href="{{ route('admin.locations.index') }}" class="hover:text-indigo-600 transition">Locations</a>
            <span>/</span>
            <span class="text-slate-900 font-bold">Edit {{ $location->name }}</span>
        </div>

        <a href="{{ route('admin.locations.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Locations</span>
        </a>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
        <div class="p-6 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-indigo-500/20 text-indigo-300 rounded-xl">
                    <i data-lucide="edit-3" class="w-6 h-6"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold">{{ __('erp.locations_management.edit_location') }} — {{ $location->name }}</h2>
                    <p class="text-xs text-slate-400">Modify location details, type, city, address or operational status</p>
                </div>
            </div>
            <span class="text-xs font-mono font-bold bg-indigo-600/30 text-indigo-300 px-3 py-1 rounded-full border border-indigo-500/30">
                {{ $location->code }}
            </span>
        </div>

        <form method="POST" action="{{ route('admin.locations.update', $location) }}" class="p-6 sm:p-8 space-y-6">
            @csrf
            @method('PUT')

            <!-- Location Type Selector -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                    {{ __('erp.locations_management.type') }} <span class="text-rose-500">*</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label class="relative flex flex-col p-4 border rounded-xl cursor-pointer transition focus-within:ring-2 focus-within:ring-indigo-500 hover:bg-slate-50/80 border-slate-200 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/40">
                        <input type="radio" name="type" value="shop" class="sr-only" {{ old('type', $location->type) === 'shop' ? 'checked' : '' }}>
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl">🏪</span>
                            <span class="text-sm font-bold text-slate-900">Shop / Branch</span>
                        </div>
                        <span class="text-[11px] text-slate-500 mt-1">Wholesale & retail shoe sales counter</span>
                    </label>

                    <label class="relative flex flex-col p-4 border rounded-xl cursor-pointer transition focus-within:ring-2 focus-within:ring-indigo-500 hover:bg-slate-50/80 border-slate-200 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/40">
                        <input type="radio" name="type" value="factory" class="sr-only" {{ old('type', $location->type) === 'factory' ? 'checked' : '' }}>
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl">🏭</span>
                            <span class="text-sm font-bold text-slate-900">Factory Unit</span>
                        </div>
                        <span class="text-[11px] text-slate-500 mt-1">Footwear manufacturing & batch production</span>
                    </label>

                    <label class="relative flex flex-col p-4 border rounded-xl cursor-pointer transition focus-within:ring-2 focus-within:ring-indigo-500 hover:bg-slate-50/80 border-slate-200 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/40">
                        <input type="radio" name="type" value="warehouse" class="sr-only" {{ old('type', $location->type) === 'warehouse' ? 'checked' : '' }}>
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl">📦</span>
                            <span class="text-sm font-bold text-slate-900">Warehouse</span>
                        </div>
                        <span class="text-[11px] text-slate-500 mt-1">Central stock & logistics hub</span>
                    </label>
                </div>
                @error('type')
                    <p class="text-xs text-rose-500 font-medium mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Name and Code -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        {{ __('erp.locations_management.name') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name', $location->name) }}" required
                           class="w-full text-sm font-semibold rounded-lg border-slate-300 p-2.5 focus:ring-2 focus:ring-indigo-500 transition @error('name') border-rose-500 @enderror">
                    @error('name')
                        <p class="text-xs text-rose-500 font-medium mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        {{ __('erp.locations_management.code') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="code" value="{{ old('code', $location->code) }}" required
                           class="w-full text-sm font-mono font-bold uppercase rounded-lg border-slate-300 p-2.5 focus:ring-2 focus:ring-indigo-500 transition @error('code') border-rose-500 @enderror">
                    @error('code')
                        <p class="text-xs text-rose-500 font-medium mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- City and Phone -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        {{ __('erp.locations_management.city') }}
                    </label>
                    <input type="text" name="city" value="{{ old('city', $location->city) }}"
                           class="w-full text-sm font-semibold rounded-lg border-slate-300 p-2.5 focus:ring-2 focus:ring-indigo-500 transition">
                    @error('city')
                        <p class="text-xs text-rose-500 font-medium mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        {{ __('erp.locations_management.phone') }}
                    </label>
                    <input type="text" name="phone" value="{{ old('phone', $location->phone) }}"
                           class="w-full text-sm font-mono font-semibold rounded-lg border-slate-300 p-2.5 focus:ring-2 focus:ring-indigo-500 transition">
                    @error('phone')
                        <p class="text-xs text-rose-500 font-medium mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Full Address -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    {{ __('erp.locations_management.address') }}
                </label>
                <textarea name="address" rows="3"
                          class="w-full text-sm font-semibold rounded-lg border-slate-300 p-2.5 focus:ring-2 focus:ring-indigo-500 transition">{{ old('address', $location->address) }}</textarea>
                @error('address')
                    <p class="text-xs text-rose-500 font-medium mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Status Checkbox -->
            <div class="pt-2">
                <label class="inline-flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $location->is_active) ? 'checked' : '' }}
                           class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300">
                    <span class="text-xs font-bold text-slate-800">
                        Location is active for transactions, inventory and orders
                    </span>
                </label>
            </div>

            <!-- Form Actions -->
            <div class="pt-6 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="{{ route('admin.locations.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md shadow-indigo-600/30 transition flex items-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Update Location</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
