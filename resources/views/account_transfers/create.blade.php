@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <!-- Breadcrumb / Back -->
    <div class="flex items-center justify-between">
        <a href="{{ route('account-transfers.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-900 transition">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Transfers</span>
        </a>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50">
            <h1 class="text-xl font-bold text-slate-900">{{ __('erp.account_transfers.new_transfer') }}</h1>
            <p class="text-xs text-slate-500 mt-1">Atomically move money between cash drawers and bank accounts. Net system impact is zero.</p>
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

        <form action="{{ route('account-transfers.store') }}" method="POST" class="p-6 space-y-5">
            @csrf

            <!-- From and To Accounts -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="from_financial_account_id" class="block text-xs font-bold text-rose-700 uppercase mb-1">
                        {{ __('erp.account_transfers.from_account') }} *
                    </label>
                    <select id="from_financial_account_id" name="from_financial_account_id" required
                            class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-rose-500 focus:bg-white focus:outline-none">
                        <option value="">Select Source Account</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ (old('from_financial_account_id', $defaultFromId) == $acc->id) ? 'selected' : '' }}>
                                {{ $acc->type === 'cash' ? '💵' : '🏦' }} {{ $acc->name }} (PKR {{ number_format((float) $acc->current_balance, 2) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="to_financial_account_id" class="block text-xs font-bold text-emerald-700 uppercase mb-1">
                        {{ __('erp.account_transfers.to_account') }} *
                    </label>
                    <select id="to_financial_account_id" name="to_financial_account_id" required
                            class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:outline-none">
                        <option value="">Select Destination Account</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ old('to_financial_account_id') == $acc->id ? 'selected' : '' }}>
                                {{ $acc->type === 'cash' ? '💵' : '🏦' }} {{ $acc->name }} (PKR {{ number_format((float) $acc->current_balance, 2) }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Amount and Date -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="amount" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.account_transfers.amount') }} *</label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount" value="{{ old('amount') }}" required placeholder="0.00"
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none font-mono text-base font-bold">
                </div>

                <div>
                    <label for="transfer_date" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.account_transfers.transfer_date') }} *</label>
                    <input type="date" id="transfer_date" name="transfer_date" value="{{ old('transfer_date', now()->toDateString()) }}" required
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                </div>
            </div>

            <!-- Reference Number -->
            <div>
                <label for="reference_number" class="block text-xs font-bold text-slate-700 uppercase mb-1">Cheque / Deposit Slip / Reference #</label>
                <input type="text" id="reference_number" name="reference_number" value="{{ old('reference_number') }}" placeholder="e.g. CHQ-48192, DEP-002"
                       class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none font-mono">
            </div>

            <!-- Notes -->
            <div>
                <label for="notes" class="block text-xs font-bold text-slate-700 uppercase mb-1">Transfer Purpose / Notes</label>
                <textarea id="notes" name="notes" rows="2" placeholder="e.g. Daily cash deposit from shop drawer to main current account..."
                          class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">{{ old('notes') }}</textarea>
            </div>

            <!-- Submit -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="{{ route('account-transfers.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50 text-sm font-semibold transition">
                    {{ __('erp.cancel') }}
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                    Execute Transfer
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
