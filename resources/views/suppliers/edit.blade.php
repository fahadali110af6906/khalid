@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.suppliers.edit_supplier') }}</h1>
            <p class="text-sm text-slate-500 mt-1">Modify supplier profile and contact parameters for {{ $supplier->business_name }}.</p>
        </div>
        <a href="{{ route('suppliers.show', $supplier) }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
            <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
            <span>Back to Supplier</span>
        </a>
    </div>

    <!-- Form Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 md:p-8">
        <form method="POST" action="{{ route('suppliers.update', $supplier) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Form Error Summary -->
            @if ($errors->any())
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
                    <p class="font-bold flex items-center gap-1.5 mb-2">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600"></i>
                        <span>Please correct the errors below:</span>
                    </p>
                    <ul class="list-disc list-inside space-y-0.5 text-xs text-rose-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Section 1: Business Identity -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2 mb-4">
                    1. Supplier / Vendor Identity
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="business_name" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.suppliers.business_name') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="business_name" name="business_name" value="{{ old('business_name', $supplier->business_name) }}" required
                               class="w-full py-2.5 px-3.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    </div>

                    <div>
                        <label for="supplier_code" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.suppliers.supplier_code') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="supplier_code" name="supplier_code" value="{{ old('supplier_code', $supplier->supplier_code) }}" required
                               class="w-full py-2.5 px-3.5 text-sm font-mono bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    </div>

                    <div>
                        <label for="contact_person" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.suppliers.contact_person') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="contact_person" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" required
                               class="w-full py-2.5 px-3.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    </div>

                    <div>
                        <label for="primary_location_id" class="block text-xs font-bold text-slate-700 mb-1">
                            Primary Dealing Location <span class="text-rose-500">*</span>
                        </label>
                        <select id="primary_location_id" name="primary_location_id" required
                                class="w-full py-2.5 px-3.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                            @foreach($authorizedLocations as $loc)
                                <option value="{{ $loc->id }}" {{ old('primary_location_id', $supplier->primary_location_id) == $loc->id ? 'selected' : '' }}>
                                    {{ $loc->type === 'factory' ? '🏭' : '🏪' }} {{ $loc->name }} ({{ ucfirst($loc->type) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 2: Contact Details -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2 mb-4">
                    2. Contact Information & Address
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="phone" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.suppliers.phone') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone', $supplier->phone) }}" required
                               class="w-full py-2.5 px-3.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none font-mono">
                    </div>

                    <div>
                        <label for="alternate_phone" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.suppliers.alternate_phone') }}
                        </label>
                        <input type="text" id="alternate_phone" name="alternate_phone" value="{{ old('alternate_phone', $supplier->alternate_phone) }}"
                               class="w-full py-2.5 px-3.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none font-mono">
                    </div>

                    <div>
                        <label for="city" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.suppliers.city') }}
                        </label>
                        <input type="text" id="city" name="city" value="{{ old('city', $supplier->city) }}"
                               class="w-full py-2.5 px-3.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    </div>

                    <div class="md:col-span-2">
                        <label for="address" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.suppliers.address') }}
                        </label>
                        <textarea id="address" name="address" rows="2"
                                  class="w-full py-2.5 px-3.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">{{ old('address', $supplier->address) }}</textarea>
                    </div>

                    <div>
                        <label for="tax_number" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.suppliers.tax_number') }}
                        </label>
                        <input type="text" id="tax_number" name="tax_number" value="{{ old('tax_number', $supplier->tax_number) }}"
                               class="w-full py-2.5 px-3.5 text-sm font-mono bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    </div>
                </div>
            </div>

            <!-- Section 3: Financial & Status -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2 mb-4">
                    3. Credit Terms & Status
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="credit_limit" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.suppliers.credit_limit') }}
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-xs font-bold text-slate-400 font-mono">PKR</span>
                            <input type="number" step="0.01" id="credit_limit" name="credit_limit" value="{{ old('credit_limit', $supplier->credit_limit) }}"
                                   class="w-full pl-12 pr-3.5 py-2.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none font-mono">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Account Active Status
                        </label>
                        <div class="mt-2.5 flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $supplier->is_active) ? 'checked' : '' }}
                                   class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500">
                            <label for="is_active" class="text-sm font-semibold text-slate-800">Supplier is active for purchasing</label>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label for="notes" class="block text-xs font-bold text-slate-700 mb-1">
                            Remarks / Commercial Notes
                        </label>
                        <textarea id="notes" name="notes" rows="2"
                                  class="w-full py-2.5 px-3.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">{{ old('notes', $supplier->notes) }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="{{ route('suppliers.show', $supplier) }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm flex items-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Save Changes</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
