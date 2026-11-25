@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.general_ledger.reconciliation') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    GL vs Sub-Ledger Integrity
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">Cross-system integrity audit comparing General Ledger master balances against operational sub-ledgers (Khata, Accounts, and Inventory).</p>
        </div>

        <div class="flex items-center gap-2.5">
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800 transition shadow-xs">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>{{ __('erp.print') }}</span>
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs print:hidden">
        <form method="GET" action="{{ route('accounting.reconciliation') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div>
                <label for="location_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.location') }}</label>
                <select id="location_id" name="location_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    <option value="">{{ __('erp.reports.all_locations') }}</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit" class="py-2 px-4 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition flex items-center justify-center gap-1.5">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>{{ __('erp.filter') }}</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Reconciliation Modules Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach($reconData['items'] as $item)
            @php
                $isMatched = bccomp((string)$item['variance'], '0.00', 2) === 0;
            @endphp
            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">{{ $item['name'] }}</h3>
                        <p class="text-xs text-slate-400 font-mono">{{ $item['gl_account'] }}</p>
                    </div>
                    @if($isMatched)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">
                            <i data-lucide="check" class="w-3.5 h-3.5 mr-1 text-emerald-600"></i> Matched
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">
                            <i data-lucide="alert-circle" class="w-3.5 h-3.5 mr-1 text-amber-600"></i> Variance
                        </span>
                    @endif
                </div>

                <div class="grid grid-cols-3 gap-2 py-3 border-y border-slate-100 text-center">
                    <div>
                        <span class="text-xs text-slate-400 uppercase font-semibold block">General Ledger</span>
                        <span class="font-mono font-bold text-sm text-slate-900">Rs. {{ number_format((float)$item['gl_balance'], 2) }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 uppercase font-semibold block">{{ $item['subledger_source'] }}</span>
                        <span class="font-mono font-bold text-sm text-slate-900">Rs. {{ number_format((float)$item['subledger_balance'], 2) }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 uppercase font-semibold block">Difference</span>
                        <span class="font-mono font-bold text-sm {{ $isMatched ? 'text-emerald-700' : 'text-amber-700' }}">
                            Rs. {{ number_format(abs((float)$item['variance']), 2) }}
                        </span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
