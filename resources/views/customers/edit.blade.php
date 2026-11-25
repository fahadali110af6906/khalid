@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.customers.edit_customer') }}</h1>
            <p class="text-sm text-slate-500 mt-1">Update profile for {{ $customer->business_name }} ({{ $customer->customer_code }}).</p>
        </div>
        <a href="{{ route('customers.show', $customer) }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
            <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
            <span>Back to Profile</span>
        </a>
    </div>

    <!-- Form Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 md:p-8">
        <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-6">
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

            <!-- Section 1: Business Profile -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2 mb-4">
                    1. Party Business Identity
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="business_name" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.customers.business_name') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="business_name" name="business_name" value="{{ old('business_name', $customer->business_name) }}" required
                               class="w-full py-2.5 px-3.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    </div>

                    <div>
                        <label for="customer_code" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.customers.customer_code') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="customer_code" name="customer_code" value="{{ old('customer_code', $customer->customer_code) }}" required
                               class="w-full py-2.5 px-3.5 text-sm font-mono bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    </div>

                    <div>
                        <label for="contact_person" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.customers.contact_person') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="contact_person" name="contact_person" value="{{ old('contact_person', $customer->contact_person) }}" required
                               class="w-full py-2.5 px-3.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    </div>

                    <div>
                        <label for="primary_location_id" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.customers.primary_location') }} <span class="text-rose-500">*</span>
                        </label>
                        <select id="primary_location_id" name="primary_location_id" required
                                class="w-full py-2.5 px-3.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                            @foreach($authorizedLocations as $loc)
                                <option value="{{ $loc->id }}" {{ old('primary_location_id', $customer->primary_location_id) == $loc->id ? 'selected' : '' }}>
                                    {{ $loc->type === 'factory' ? '🏭' : '🏪' }} {{ $loc->name }} ({{ $loc->city }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 2: Contact & Address -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2 mb-4">
                    2. Communication & Shipping Details
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="phone" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.customers.phone') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone', $customer->phone) }}" required
                               class="w-full py-2.5 px-3.5 text-sm font-mono bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    </div>

                    <div>
                        <label for="alternate_phone" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.customers.alternate_phone') }}
                        </label>
                        <input type="text" id="alternate_phone" name="alternate_phone" value="{{ old('alternate_phone', $customer->alternate_phone) }}"
                               class="w-full py-2.5 px-3.5 text-sm font-mono bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    </div>

                    <div>
                        <label for="city" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.customers.city') }}
                        </label>
                        <input type="text" id="city" name="city" value="{{ old('city', $customer->city) }}"
                               class="w-full py-2.5 px-3.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label for="address" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.customers.address') }}
                        </label>
                        <textarea id="address" name="address" rows="2"
                                  class="w-full py-2 px-3.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">{{ old('address', $customer->address) }}</textarea>
                    </div>

                    <div>
                        <label for="email" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.customers.email') }}
                        </label>
                        <input type="email" id="email" name="email" value="{{ old('email', $customer->email) }}"
                               class="w-full py-2.5 px-3.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">

                        <label for="tax_number" class="block text-xs font-bold text-slate-700 mt-2 mb-1">
                            {{ __('erp.customers.tax_number') }}
                        </label>
                        <input type="text" id="tax_number" name="tax_number" value="{{ old('tax_number', $customer->tax_number) }}"
                               class="w-full py-2 px-3.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    </div>
                </div>
            </div>

            <!-- Section 3: Commercial & Credit Terms -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2 mb-4">
                    3. Commercial Terms & Status
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="credit_limit" class="block text-xs font-bold text-slate-700 mb-1">
                            {{ __('erp.customers.credit_limit') }}
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-xs font-bold text-slate-400">PKR</span>
                            <input type="number" step="0.01" min="0" id="credit_limit" name="credit_limit" value="{{ old('credit_limit', $customer->credit_limit) }}"
                                   class="w-full pl-12 pr-3 py-2.5 text-sm font-mono font-bold bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Account Operational Status
                        </label>
                        <div class="mt-2 flex items-center gap-4">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="is_active" value="1" {{ old('is_active', $customer->is_active) ? 'checked' : '' }}
                                       class="text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm font-semibold text-slate-800">{{ __('erp.customers.active') }}</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="is_active" value="0" {{ !old('is_active', $customer->is_active) ? 'checked' : '' }}
                                       class="text-rose-600 focus:ring-rose-500">
                                <span class="text-sm font-semibold text-rose-700">{{ __('erp.customers.inactive') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <label for="notes" class="block text-xs font-bold text-slate-700 mb-1">
                        Commercial Notes / Delivery Terms
                    </label>
                    <textarea id="notes" name="notes" rows="2"
                              class="w-full py-2 px-3.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">{{ old('notes', $customer->notes) }}</textarea>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('customers.show', $customer) }}" class="px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-sm font-bold transition">
                    {{ __('erp.cancel') }}
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                    {{ __('erp.save') }} Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
