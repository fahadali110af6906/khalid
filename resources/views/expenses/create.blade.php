@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Breadcrumb / Back -->
    <div class="flex items-center justify-between">
        <a href="{{ route('expenses.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-900 transition">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Expenses</span>
        </a>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50">
            <h1 class="text-xl font-bold text-slate-900">{{ __('erp.expenses.new_expense') }}</h1>
            <p class="text-xs text-slate-500 mt-1">Record shop overhead, utility, rent, or factory operational expenses.</p>
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

        <form action="{{ route('expenses.store') }}" method="POST" class="p-6 space-y-5">
            @csrf

            <!-- Location & Category -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="location_id" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.location') }} *</label>
                    <select id="location_id" name="location_id" required class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                        @foreach($authorizedLocations as $loc)
                            <option value="{{ $loc->id }}" {{ old('location_id') == $loc->id ? 'selected' : '' }}>
                                {{ $loc->type === 'factory' ? '🏭' : '🏪' }} {{ $loc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="expense_category_id" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.expenses.category') }} *</label>
                    <select id="expense_category_id" name="expense_category_id" required class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                        <option value="">Select Expense Category</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('expense_category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }} ({{ $cat->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Amount and Date -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="amount" class="block text-xs font-bold text-slate-700 uppercase mb-1">Expense Amount (PKR) *</label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount" value="{{ old('amount') }}" required placeholder="0.00"
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none font-mono text-base font-bold">
                </div>

                <div>
                    <label for="expense_date" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.expenses.expense_date') }} *</label>
                    <input type="date" id="expense_date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" required
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                </div>
            </div>

            <!-- Payee Name & Payment Method -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="payee_name" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.expenses.payee') }}</label>
                    <input type="text" id="payee_name" name="payee_name" value="{{ old('payee_name') }}" placeholder="e.g. Landlord, WAPDA, Driver, Vendor"
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label for="payment_method" class="block text-xs font-bold text-slate-700 uppercase mb-1">Payment Method *</label>
                    <select id="payment_method" name="payment_method" required class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                        <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Cash</option>
                        <option value="bank" {{ old('payment_method') === 'bank' ? 'selected' : '' }}>Bank Account</option>
                        <option value="cheque" {{ old('payment_method') === 'cheque' ? 'selected' : '' }}>Cheque</option>
                        <option value="online_transfer" {{ old('payment_method') === 'online_transfer' ? 'selected' : '' }}>Online Transfer</option>
                    </select>
                </div>
            </div>

            <!-- Financial Account & Reference Number -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="financial_account_id" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.expenses.paid_from') }}</label>
                    <select id="financial_account_id" name="financial_account_id" class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                        <option value="">None / Unassigned (Draft only)</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ old('financial_account_id') == $acc->id ? 'selected' : '' }}>
                                {{ $acc->type === 'cash' ? '💵' : '🏦' }} {{ $acc->name }} (PKR {{ number_format((float) $acc->current_balance, 2) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="reference_number" class="block text-xs font-bold text-slate-700 uppercase mb-1">Bill / Cheque / Ref #</label>
                    <input type="text" id="reference_number" name="reference_number" value="{{ old('reference_number') }}" placeholder="e.g. BILL-9921, CHQ-551"
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none font-mono">
                </div>
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-xs font-bold text-slate-700 uppercase mb-1">Description</label>
                <input type="text" id="description" name="description" value="{{ old('description') }}" placeholder="Brief description of the expense..."
                       class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
            </div>

            <!-- Notes -->
            <div>
                <label for="notes" class="block text-xs font-bold text-slate-700 uppercase mb-1">Additional Notes</label>
                <textarea id="notes" name="notes" rows="2" placeholder="Optional audit notes or billing remarks..."
                          class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">{{ old('notes') }}</textarea>
            </div>

            <!-- Post Immediately Checkbox -->
            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/80">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="post_immediately" value="1" {{ old('post_immediately', true) ? 'checked' : '' }}
                           class="w-4 h-4 mt-0.5 rounded text-indigo-600 border-slate-300 focus:ring-indigo-500">
                    <div>
                        <span class="text-sm font-bold text-slate-800">Post Immediately</span>
                        <p class="text-xs text-slate-500 mt-0.5">Deduct amount from the selected Cash/Bank account immediately. (Uncheck to save as draft voucher without ledger impact).</p>
                    </div>
                </label>
            </div>

            <!-- Submit -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="{{ route('expenses.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50 text-sm font-semibold transition">
                    {{ __('erp.cancel') }}
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                    {{ __('erp.save') }} Expense Voucher
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
