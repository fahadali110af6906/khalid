@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('accounting.chart-of-accounts.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-slate-800 transition">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.chart_of_accounts.new_account') }}</h1>
                <p class="text-sm text-slate-500 mt-0.5">Define a new General Ledger account code and classification.</p>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
            <div class="font-bold mb-1 flex items-center gap-1.5">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                Please correct the following errors:
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-xs font-semibold">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('accounting.chart-of-accounts.store') }}" class="space-y-6">
        @csrf

        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-5">
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                <i data-lucide="tag" class="w-4 h-4 text-indigo-600"></i>
                Account Identification & Hierarchy
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="code" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.chart_of_accounts.account_code') }} *</label>
                    <input type="text" id="code" name="code" value="{{ old('code') }}" required
                           placeholder="e.g., 1170, 2140, 6800"
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white font-mono font-bold">
                </div>

                <div>
                    <label for="name" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.chart_of_accounts.account_name') }} *</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                           placeholder="e.g., Packing Tape & Consumables"
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                </div>

                <div>
                    <label for="type" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.chart_of_accounts.account_type') }} *</label>
                    <select id="type" name="type" required
                            class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                        <option value="asset" {{ old('type') === 'asset' ? 'selected' : '' }}>Asset (1000s)</option>
                        <option value="liability" {{ old('type') === 'liability' ? 'selected' : '' }}>Liability (2000s)</option>
                        <option value="equity" {{ old('type') === 'equity' ? 'selected' : '' }}>Equity (3000s)</option>
                        <option value="revenue" {{ old('type') === 'revenue' ? 'selected' : '' }}>Revenue / Income (4000s)</option>
                        <option value="expense" {{ old('type') === 'expense' ? 'selected' : '' }}>Cost of Goods Sold & Expenses (5000-6000s)</option>
                    </select>
                </div>

                <div>
                    <label for="parent_id" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.chart_of_accounts.parent_account') }}</label>
                    <select id="parent_id" name="parent_id"
                            class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                        <option value="">No Parent (Top Group Level)</option>
                        @foreach($groupAccounts as $grp)
                            <option value="{{ $grp->id }}" {{ old('parent_id') == $grp->id ? 'selected' : '' }}>
                                {{ $grp->code }} — {{ $grp->name }} ({{ ucfirst($grp->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="normal_balance" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.chart_of_accounts.normal_balance') }} *</label>
                    <select id="normal_balance" name="normal_balance" required
                            class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                        <option value="debit" {{ old('normal_balance', 'debit') === 'debit' ? 'selected' : '' }}>Debit Normal (Assets, Expenses)</option>
                        <option value="credit" {{ old('normal_balance') === 'credit' ? 'selected' : '' }}>Credit Normal (Liabilities, Equity, Revenue)</option>
                    </select>
                </div>

                <div class="flex items-center pt-5">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_group" value="1" {{ old('is_group') ? 'checked' : '' }} class="w-4 h-4 text-indigo-600 rounded">
                        <span class="text-xs font-bold text-slate-800">{{ __('erp.chart_of_accounts.is_group') }}</span>
                    </label>
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="block text-xs font-bold text-slate-700 uppercase mb-1">Description / Usage Purpose</label>
                    <textarea id="description" name="description" rows="2" placeholder="Specify what transactions are posted to this account..."
                              class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500">{{ old('description') }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('accounting.chart-of-accounts.index') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">
                {{ __('erp.cancel') }}
            </a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-sm transition flex items-center gap-2">
                <i data-lucide="check" class="w-4 h-4"></i>
                Save Account
            </button>
        </div>
    </form>
</div>
@endsection
