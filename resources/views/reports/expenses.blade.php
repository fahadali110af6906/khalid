@extends('layouts.app')

@section('title', __('erp.reports.expenses_report') . ' — ' . __('erp.brand_name'))

@section('content')
<div class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase text-rose-600 tracking-wider mb-1">
                    <a href="{{ route('reports.index') }}" class="hover:underline">Reports</a>
                    <span>/</span>
                    <span>Expenditures</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ __('erp.reports.expenses_report') }}</h1>
                <p class="text-xs text-slate-500 mt-1">Operating expense breakdown by category, payment account, location, and voucher lifecycle status.</p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('reports.expenses.export', request()->query()) }}" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs px-4 py-2.5 rounded-lg shadow-xs transition">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span>{{ __('erp.reports.export_csv') }}</span>
                </a>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('reports.expenses') }}" class="mt-6 pt-5 border-t border-slate-100 flex flex-wrap items-center gap-3">
            @if(auth()->user()->isOwner())
            <div>
                <select name="location_id" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 font-medium text-slate-700">
                    <option value="">🏢 {{ __('erp.reports.select_location') }}</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ $currentLocationId == $loc->id ? 'selected' : '' }}>
                            {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div>
                <select name="expense_category_id" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 font-medium text-slate-700">
                    <option value="">🏷️ All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ $currentCategoryId == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <select name="status" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 font-medium text-slate-700">
                    <option value="">Status: All</option>
                    <option value="posted" {{ $currentStatus === 'posted' ? 'selected' : '' }}>Posted Only (Recognized)</option>
                    <option value="draft" {{ $currentStatus === 'draft' ? 'selected' : '' }}>Draft Only (Pending)</option>
                    <option value="reversed" {{ $currentStatus === 'reversed' ? 'selected' : '' }}>Reversed Only (Annulled)</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <input type="date" name="from_date" value="{{ $filters['from_date'] }}" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-2 font-medium text-slate-700">
                <span class="text-xs text-slate-400">to</span>
                <input type="date" name="to_date" value="{{ $filters['to_date'] }}" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-2 font-medium text-slate-700">
            </div>

            <button type="submit" class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-lg transition shadow-xs">
                {{ __('erp.reports.filter_apply') }}
            </button>
            <a href="{{ route('reports.expenses') }}" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold px-3 py-2 rounded-lg transition">
                {{ __('erp.reports.filter_reset') }}
            </a>
        </form>
    </div>

    <!-- Status Breakdown Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Posted Expenses (Recognized) -->
        <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-rose-700">Posted Operating Expenses</span>
                <span class="text-[10px] bg-rose-100 text-rose-800 font-bold px-2 py-0.5 rounded uppercase">Recognized</span>
            </div>
            <div class="text-2xl font-black text-rose-600 tracking-tight">PKR {{ number_format((float) $summary['posted_amount'], 2) }}</div>
            <div class="text-xs text-slate-500 mt-2">{{ $summary['posted_count'] }} Posted vouchers deducting cash/bank</div>
        </div>

        <!-- Draft Expenses (Pending) -->
        <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-700">Draft Vouchers (Pending)</span>
                <span class="text-[10px] bg-amber-100 text-amber-800 font-bold px-2 py-0.5 rounded uppercase">Unposted</span>
            </div>
            <div class="text-2xl font-black text-amber-600 tracking-tight">PKR {{ number_format((float) $summary['draft_amount'], 2) }}</div>
            <div class="text-xs text-slate-500 mt-2">{{ $summary['draft_count'] }} Draft vouchers (Zero cash impact)</div>
        </div>

        <!-- Reversed Expenses (Restored) -->
        <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Reversed Vouchers</span>
                <span class="text-[10px] bg-slate-100 text-slate-800 font-bold px-2 py-0.5 rounded uppercase">Annulled</span>
            </div>
            <div class="text-2xl font-black text-slate-600 tracking-tight">PKR {{ number_format((float) $summary['reversed_amount'], 2) }}</div>
            <div class="text-xs text-slate-500 mt-2">{{ $summary['reversed_count'] }} Reversed (Funds restored via counter-entry)</div>
        </div>
    </div>

    <!-- Category & Account Breakdown Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- By Category -->
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden">
            <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200/80 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Posted Expenses by Category</h2>
                <span class="text-xs text-slate-500">{{ count($category_breakdown) }} Categories</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-start">
                    <thead class="bg-slate-100 text-slate-600 font-bold uppercase border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-start">Category</th>
                            <th class="px-4 py-3 text-end">Vouchers</th>
                            <th class="px-4 py-3 text-end">Amount (PKR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                        @forelse($category_breakdown as $cb)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-4 py-3 font-semibold text-slate-900">{{ $cb['name'] }}</td>
                            <td class="px-4 py-3 text-end text-slate-600">{{ $cb['count'] }}</td>
                            <td class="px-4 py-3 text-end font-bold text-rose-600 font-mono">PKR {{ number_format((float) $cb['amount'], 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-slate-400">No posted category expenses.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- By Account -->
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden">
            <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200/80 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Paid From Financial Account</h2>
                <span class="text-xs text-slate-500">{{ count($account_breakdown) }} Accounts</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-start">
                    <thead class="bg-slate-100 text-slate-600 font-bold uppercase border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-start">Account</th>
                            <th class="px-4 py-3 text-end">Vouchers</th>
                            <th class="px-4 py-3 text-end">Amount Paid (PKR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                        @forelse($account_breakdown as $ab)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-4 py-3 font-semibold text-slate-900">{{ $ab['name'] }}</td>
                            <td class="px-4 py-3 text-end text-slate-600">{{ $ab['count'] }}</td>
                            <td class="px-4 py-3 text-end font-bold text-rose-600 font-mono">PKR {{ number_format((float) $ab['amount'], 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-slate-400">No account deduction records.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Expenses Vouchers Table -->
    <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200/80 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Expense Vouchers Log ({{ $expenses->count() }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-start">
                <thead class="bg-slate-100 text-slate-600 font-bold uppercase border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-start">Voucher #</th>
                        <th class="px-4 py-3 text-start">Date</th>
                        <th class="px-4 py-3 text-start">Category</th>
                        <th class="px-4 py-3 text-start">Paid From</th>
                        <th class="px-4 py-3 text-start">Payee</th>
                        <th class="px-4 py-3 text-start">Description</th>
                        <th class="px-4 py-3 text-end">Amount (PKR)</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                    @forelse($expenses as $exp)
                    <tr class="hover:bg-slate-50/70 transition">
                        <td class="px-4 py-3 font-mono font-bold text-rose-600">
                            <a href="{{ route('expenses.show', $exp->id) }}" class="hover:underline">
                                {{ $exp->expense_number }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $exp->expense_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-900">{{ $exp->category ? $exp->category->name : 'N/A' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $exp->account ? $exp->account->name : 'N/A' }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $exp->payee_name ?: '-' }}</td>
                        <td class="px-4 py-3 text-slate-600 max-w-xs truncate">{{ $exp->description }}</td>
                        <td class="px-4 py-3 text-end font-bold text-slate-900 font-mono">PKR {{ number_format((float) $exp->amount, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $exp->isPosted() ? 'bg-emerald-100 text-emerald-800' : ($exp->isDraft() ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-800') }}">
                                {{ $exp->status }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-slate-400">
                            {{ __('erp.reports.no_data') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
