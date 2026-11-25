@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Breadcrumb / Back -->
    <div class="flex items-center justify-between">
        <a href="{{ route('financial-accounts.show', $financialAccount) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-900 transition">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Account</span>
        </a>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Edit Account: {{ $financialAccount->name }}</h1>
                <p class="text-xs text-slate-500 mt-1">Code: <span class="font-mono font-bold">{{ $financialAccount->account_code }}</span> ({{ ucfirst($financialAccount->type) }})</p>
            </div>
            <span class="font-mono font-bold text-sm px-3 py-1 rounded-lg bg-slate-100 text-slate-800">
                Balance: PKR {{ number_format((float) $financialAccount->current_balance, 2) }}
            </span>
        </div>

        @if(session('error'))
            <div class="m-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium flex items-center gap-2">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="m-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm space-y-1">
                @foreach($errors->all() as $err)
                    <p class="flex items-center gap-2"><i data-lucide="x" class="w-4 h-4 text-rose-600"></i> {{ $err }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('financial-accounts.update', $financialAccount) }}" method="POST" class="p-6 space-y-5">
            @csrf
            @method('PUT')

            <!-- Account Name -->
            <div>
                <label for="name" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.financial_accounts.account_name') }} *</label>
                <input type="text" id="name" name="name" value="{{ old('name', $financialAccount->name) }}" required
                       class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
            </div>

            <!-- Bank Fields (conditional) -->
            @if($financialAccount->type === 'bank')
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/80 space-y-4">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Bank Details</div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="bank_name" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.financial_accounts.bank_name') }}</label>
                            <input type="text" id="bank_name" name="bank_name" value="{{ old('bank_name', $financialAccount->bank_name) }}"
                                   class="w-full py-2 px-3 text-sm bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label for="account_number" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.financial_accounts.account_number') }}</label>
                            <input type="text" id="account_number" name="account_number" value="{{ old('account_number', $financialAccount->account_number) }}"
                                   class="w-full py-2 px-3 text-sm bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none font-mono">
                        </div>
                        <div>
                            <label for="branch_code" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.financial_accounts.branch_code') }}</label>
                            <input type="text" id="branch_code" name="branch_code" value="{{ old('branch_code', $financialAccount->branch_code) }}"
                                   class="w-full py-2 px-3 text-sm bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                    </div>
                </div>
            @endif

            <!-- Location & Active Status -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="location_id" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.financial_accounts.location') }}</label>
                    <select id="location_id" name="location_id" class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                        <option value="">🏢 Central (All Locations)</option>
                        @foreach($authorizedLocations as $loc)
                            <option value="{{ $loc->id }}" {{ old('location_id', $financialAccount->location_id) == $loc->id ? 'selected' : '' }}>
                                {{ $loc->type === 'factory' ? '🏭' : '🏪' }} {{ $loc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center pt-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $financialAccount->is_active) ? 'checked' : '' }}
                               class="w-4 h-4 rounded text-indigo-600 border-slate-300 focus:ring-indigo-500">
                        <span class="text-sm font-semibold text-slate-800">Account is Active</span>
                    </label>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label for="notes" class="block text-xs font-bold text-slate-700 uppercase mb-1">Notes / Description</label>
                <textarea id="notes" name="notes" rows="2"
                          class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">{{ old('notes', $financialAccount->notes) }}</textarea>
            </div>

            <!-- Submit -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="{{ route('financial-accounts.show', $financialAccount) }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50 text-sm font-semibold transition">
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
