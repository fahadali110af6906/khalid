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
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Edit Account: {{ $account->code }}</h1>
                <p class="text-sm text-slate-500 mt-0.5">{{ $account->name }}</p>
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

    <form method="POST" action="{{ route('accounting.chart-of-accounts.update', $account) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-5">
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                <i data-lucide="tag" class="w-4 h-4 text-indigo-600"></i>
                Account Specification
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="code" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.chart_of_accounts.account_code') }} *</label>
                    <input type="text" id="code" name="code" value="{{ old('code', $account->code) }}" required
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white font-mono font-bold">
                </div>

                <div>
                    <label for="name" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.chart_of_accounts.account_name') }} *</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $account->name) }}" required
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">{{ __('erp.chart_of_accounts.account_type') }}</label>
                    <input type="text" value="{{ ucfirst($account->type) }}" readonly
                           class="w-full py-2.5 px-3 text-sm bg-slate-100 border border-slate-200 rounded-lg text-slate-500 font-semibold cursor-not-allowed">
                </div>

                <div>
                    <label for="parent_id" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.chart_of_accounts.parent_account') }}</label>
                    <select id="parent_id" name="parent_id"
                            class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                        <option value="">No Parent (Top Group Level)</option>
                        @foreach($groupAccounts as $grp)
                            <option value="{{ $grp->id }}" {{ old('parent_id', $account->parent_id) == $grp->id ? 'selected' : '' }}>
                                {{ $grp->code }} — {{ $grp->name }} ({{ ucfirst($grp->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center pt-5">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $account->is_active) ? 'checked' : '' }}
                               {{ $account->is_system ? 'disabled' : '' }} class="w-4 h-4 text-emerald-600 rounded">
                        <span class="text-xs font-bold text-slate-800">Active Account</span>
                    </label>
                    @if($account->is_system)
                        <input type="hidden" name="is_active" value="1">
                        <span class="ms-2 text-[11px] text-amber-600 font-semibold">(System accounts cannot be deactivated)</span>
                    @endif
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="block text-xs font-bold text-slate-700 uppercase mb-1">Description / Usage Purpose</label>
                    <textarea id="description" name="description" rows="2"
                              class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500">{{ old('description', $account->description) }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('accounting.chart-of-accounts.index') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">
                {{ __('erp.cancel') }}
            </a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-sm transition flex items-center gap-2">
                <i data-lucide="check" class="w-4 h-4"></i>
                Update Account
            </button>
        </div>
    </form>
</div>
@endsection
