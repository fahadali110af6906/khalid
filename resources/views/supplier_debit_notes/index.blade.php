@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.supplier_debit_notes.title') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
                    Payables Reductions & Claims
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ __('erp.supplier_debit_notes.subtitle') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('supplier-debit-notes.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>{{ __('erp.supplier_debit_notes.new_debit_note') }}</span>
            </a>
            <a href="{{ route('purchases.returns.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold transition">
                <i data-lucide="corner-up-left" class="w-4 h-4"></i>
                <span>{{ __('erp.purchase_returns.title') }}</span>
            </a>
        </div>
    </div>

    <!-- Alert Success / Error -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Debit Notes</p>
                <p class="text-2xl font-black text-slate-900 mt-1 font-mono">{{ $debitNotes->total() }}</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600">
                <i data-lucide="file-minus" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Stock-Related</p>
                <p class="text-2xl font-black text-amber-600 mt-1 font-mono">
                    {{ $debitNotes->where('debit_note_type', 'purchase_return')->count() }}
                </p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600">
                <i data-lucide="package-minus" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Financial Only</p>
                <p class="text-2xl font-black text-purple-600 mt-1 font-mono">
                    {{ $debitNotes->where('debit_note_type', 'financial_adjustment')->count() }}
                </p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-purple-50 border border-purple-100 flex items-center justify-center text-purple-600">
                <i data-lucide="badge-percent" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Page Total Debited</p>
                <p class="text-xl font-black text-slate-900 mt-1 font-mono">
                    ₨ {{ number_format($debitNotes->sum('amount'), 2) }}
                </p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600">
                <i data-lucide="calculator" class="w-6 h-6"></i>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('supplier-debit-notes.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label for="search" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.search') }}</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="{{ $search }}"
                           placeholder="DN #, reference, reason..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                </div>
            </div>

            <div>
                <label for="supplier_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">Supplier</label>
                <select id="supplier_id" name="supplier_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" {{ $supplierId == $s->id ? 'selected' : '' }}>
                            {{ $s->business_name }} ({{ $s->supplier_code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="location_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.location') }}</label>
                <select id="location_id" name="location_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    @if(auth()->user()->isOwner())
                        <option value="all" {{ $locationId === 'all' || !$locationId ? 'selected' : '' }}>🏢 {{ __('erp.all_locations') }}</option>
                    @endif
                    @foreach($authorizedLocations as $loc)
                        <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>
                            {{ $loc->type === 'factory' ? '🏭' : '🏪' }} {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="type" class="block text-xs font-bold text-slate-600 uppercase mb-1">Type</label>
                <select id="type" name="type" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="all" {{ $type === 'all' ? 'selected' : '' }}>All Types</option>
                    <option value="purchase_return" {{ $type === 'purchase_return' ? 'selected' : '' }}>Stock Return</option>
                    <option value="financial_adjustment" {{ $type === 'financial_adjustment' ? 'selected' : '' }}>Financial Only (Discount/Claim)</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-4 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-bold transition flex items-center justify-center gap-1.5">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>{{ __('erp.filter') }}</span>
                </button>
                <a href="{{ route('supplier-debit-notes.index') }}" class="py-2 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold transition" title="Reset filter">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Debit Notes Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-xs">
                <thead class="bg-slate-50 text-slate-600 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">{{ __('erp.supplier_debit_notes.debit_note_number') }}</th>
                        <th class="py-3 px-4">{{ __('erp.date') }}</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Supplier</th>
                        <th class="py-3 px-4">{{ __('erp.location') }}</th>
                        <th class="py-3 px-4">Reason / Description</th>
                        <th class="py-3 px-4 text-end">Amount (PKR)</th>
                        <th class="py-3 px-4 text-end">{{ __('erp.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($debitNotes as $note)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                <a href="{{ route('supplier-debit-notes.show', $note) }}" class="text-indigo-600 hover:underline">
                                    {{ $note->debit_note_number }}
                                </a>
                                @if($note->reference_number)
                                    <span class="block text-[10px] text-slate-400 font-mono">Ref: {{ $note->reference_number }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-slate-600 font-mono">
                                {{ $note->note_date ? $note->note_date->format('d M Y') : '-' }}
                            </td>
                            <td class="py-3 px-4">
                                @if($note->debit_note_type === 'purchase_return')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                        📦 Stock Return
                                    </span>
                                    @if($note->purchaseReturn)
                                        <a href="{{ route('purchases.returns.show', $note->purchaseReturn) }}" class="block text-[10px] text-indigo-600 font-mono hover:underline mt-0.5">
                                            {{ $note->purchaseReturn->return_number }}
                                        </a>
                                    @endif
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800">
                                        💰 Financial Only
                                    </span>
                                    <span class="block text-[10px] text-slate-400 capitalize mt-0.5">
                                        {{ str_replace('_', ' ', $note->reason_type ?? 'Adjustment') }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-bold text-slate-800 block">{{ $note->supplier->business_name }}</span>
                                <span class="text-[10px] text-slate-400 font-mono">{{ $note->supplier->supplier_code }}</span>
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                {{ $note->location->name }}
                            </td>
                            <td class="py-3 px-4 text-slate-700 max-w-xs truncate" title="{{ $note->reason }}">
                                {{ $note->reason }}
                            </td>
                            <td class="py-3 px-4 text-end font-mono font-bold text-rose-700">
                                PKR {{ number_format($note->amount, 2) }}
                            </td>
                            <td class="py-3 px-4 text-end">
                                <a href="{{ route('supplier-debit-notes.show', $note) }}"
                                   class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-indigo-50 hover:text-indigo-600 text-slate-700 text-xs font-semibold transition">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                    <span>View</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-400">
                                <i data-lucide="file-minus" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                                <p class="text-sm font-semibold">No supplier debit notes recorded.</p>
                                <p class="text-xs text-slate-400 mt-1">
                                    Debit notes are automatically created upon posting a purchase return or recorded directly for supplier discounts & allowances.
                                </p>
                                <a href="{{ route('supplier-debit-notes.create') }}" class="inline-flex items-center gap-1.5 mt-3 px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold hover:bg-indigo-100 transition">
                                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                    <span>Create Financial Debit Note</span>
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($debitNotes->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $debitNotes->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
