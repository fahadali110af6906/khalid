@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <!-- Top Navigation Breadcrumb -->
    <div class="flex items-center justify-between">
        <a href="{{ route('employees.index', ['location_id' => $employee->location_id]) }}" 
           class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-slate-800 transition">
            <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
            <span>{{ app()->getLocale() === 'ur' ? 'واپس ملازمین فہرست' : 'Back to Employees' }}</span>
        </a>

        <div class="flex items-center gap-2">
            <a href="{{ route('employees.edit', $employee->id) }}" 
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold transition shadow-2xs">
                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                <span>{{ app()->getLocale() === 'ur' ? 'ترمیم کریں' : 'Edit Profile' }}</span>
            </a>
        </div>
    </div>

    <!-- Employee Profile Summary Card -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-6 sm:p-7">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-slate-100">
            <!-- Left: Avatar & Identity -->
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-teal-500 to-indigo-600 flex items-center justify-center text-white font-black text-xl shadow-lg shadow-teal-500/20">
                    {{ mb_substr($employee->name, 0, 2) }}
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">{{ $employee->name }}</h1>
                        @if($employee->status === 'active')
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                <span>{{ app()->getLocale() === 'ur' ? 'فعال' : 'Active' }}</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
                                <span>{{ ucfirst($employee->status) }}</span>
                            </span>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-1.5 text-xs text-slate-500">
                        <span class="font-mono bg-slate-100 px-2 py-0.5 rounded font-bold text-slate-700">{{ $employee->employee_code }}</span>
                        <span>•</span>
                        <span class="font-semibold text-slate-700">{{ $employee->designation }}</span>
                        <span>•</span>
                        <span class="inline-flex items-center gap-1 font-bold text-teal-700">
                            <span>{{ $employee->location->type === 'shop' ? '🏪' : ($employee->location->type === 'factory' ? '🏭' : '📦') }}</span>
                            <span>{{ $employee->location->name }}</span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right: Base Salary Highlight -->
            <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white p-4 rounded-2xl shadow-sm min-w-[220px]">
                <span class="text-[10px] font-bold uppercase tracking-wider text-teal-400">
                    {{ app()->getLocale() === 'ur' ? 'ماہانہ بنیادی تنخواہ' : 'Base Monthly Salary' }}
                </span>
                <div class="text-2xl font-black font-mono text-white mt-1">
                    PKR {{ number_format($employee->monthly_salary, 0) }}
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5 capitalize">
                    {{ app()->getLocale() === 'ur' ? 'نوعیت: ' : 'Type: ' }} {{ $employee->employment_type }}
                    @if($employee->daily_rate)
                        (PKR {{ number_format($employee->daily_rate, 0) }}/day)
                    @endif
                </div>
            </div>
        </div>

        <!-- Personal & Contact Details Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 pt-5 text-xs">
            <div>
                <span class="text-[10px] font-bold uppercase text-slate-400 block">{{ app()->getLocale() === 'ur' ? 'فون نمبر' : 'Phone' }}</span>
                <span class="font-bold text-slate-800">{{ $employee->phone ?? '—' }}</span>
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase text-slate-400 block">{{ app()->getLocale() === 'ur' ? 'شناختی کارڈ' : 'CNIC' }}</span>
                <span class="font-mono font-semibold text-slate-700">{{ $employee->cnic ?? '—' }}</span>
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase text-slate-400 block">{{ app()->getLocale() === 'ur' ? 'شہر' : 'City' }}</span>
                <span class="font-semibold text-slate-800">{{ $employee->city ?? 'Lahore' }}</span>
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase text-slate-400 block">{{ app()->getLocale() === 'ur' ? 'شمولیت کی تاریخ' : 'Joined Date' }}</span>
                <span class="font-semibold text-slate-800">{{ $employee->joining_date ? $employee->joining_date->format('d M Y') : '—' }}</span>
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase text-slate-400 block">{{ app()->getLocale() === 'ur' ? 'ہنگامی رابطہ' : 'Emergency Contact' }}</span>
                <span class="font-semibold text-slate-800">
                    {{ $employee->emergency_contact_name ?? '—' }}
                    @if($employee->emergency_contact_phone)
                        <div class="text-[11px] text-slate-500 font-mono">{{ $employee->emergency_contact_phone }}</div>
                    @endif
                </span>
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase text-slate-400 block">{{ app()->getLocale() === 'ur' ? 'رہائشی پتہ' : 'Address' }}</span>
                <span class="text-slate-700 truncate block max-w-xs">{{ $employee->address ?? '—' }}</span>
            </div>
        </div>
    </div>

    <!-- 4 Khata Balance Overview Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Total Salary Paid -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                    {{ app()->getLocale() === 'ur' ? 'کل ادا شدہ تنخواہ' : 'Total Salaries Paid' }}
                </span>
                <div class="p-2 bg-emerald-50 text-emerald-600 rounded-lg">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-emerald-600 mt-2 font-mono">
                PKR {{ number_format($totalPaid, 0) }}
            </div>
            <div class="text-xs text-slate-400 mt-1">{{ app()->getLocale() === 'ur' ? 'اب تک ادا شدہ رقم' : 'All-time payouts' }}</div>
        </div>

        <!-- 2. Total Advances / Kharcha -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                    {{ app()->getLocale() === 'ur' ? 'کل پیشگی خرچہ / ادھار' : 'Total Advances (Kharcha)' }}
                </span>
                <div class="p-2 bg-amber-50 text-amber-600 rounded-lg">
                    <i data-lucide="credit-card" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-amber-600 mt-2 font-mono">
                PKR {{ number_format($totalAdvances, 0) }}
            </div>
            <div class="text-xs text-slate-400 mt-1">{{ app()->getLocale() === 'ur' ? 'دورانِ مہینہ پیشگی دیا گیا خرچہ' : 'Advances disbursed' }}</div>
        </div>

        <!-- 3. Total Bonuses -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                    {{ app()->getLocale() === 'ur' ? 'کل بونس و انعامات' : 'Total Bonuses' }}
                </span>
                <div class="p-2 bg-purple-50 text-purple-600 rounded-lg">
                    <i data-lucide="gift" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-purple-700 mt-2 font-mono">
                PKR {{ number_format($totalBonuses, 0) }}
            </div>
            <div class="text-xs text-slate-400 mt-1">{{ app()->getLocale() === 'ur' ? 'انعامات و اضافی الاؤنس' : 'Extra performance bonuses' }}</div>
        </div>

        <!-- 4. Net Pending Advance Balance -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                    {{ app()->getLocale() === 'ur' ? 'بقیہ پیشگی خرچہ (زیرِ کٹوتی)' : 'Pending Advance Balance' }}
                </span>
                <div class="p-2 bg-rose-50 text-rose-600 rounded-lg">
                    <i data-lucide="scale" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-black {{ $pendingAdvanceBalance > 0 ? 'text-rose-600' : 'text-slate-800' }} mt-2 font-mono">
                PKR {{ number_format($pendingAdvanceBalance, 0) }}
            </div>
            <div class="text-xs text-slate-400 mt-1">{{ app()->getLocale() === 'ur' ? 'اگلی تنخواہ سے کٹنے والا پیشگی خرچہ' : 'Recoverable from salary' }}</div>
        </div>
    </div>

    <!-- Quick Action: Record Payout or Advance Kharcha -->
    <div x-data="{ tab: 'advance' }" class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
        <!-- Tab Bar -->
        <div class="bg-slate-900 text-white px-6 py-3 flex flex-wrap items-center justify-between gap-4 border-b border-slate-800">
            <div class="flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-4 h-4 text-teal-400"></i>
                <span class="text-xs font-bold uppercase tracking-wider">{{ app()->getLocale() === 'ur' ? 'کھاتے میں نیا اندراج درج کریں' : 'Record New Transaction' }}</span>
            </div>

            <div class="inline-flex rounded-xl bg-slate-800 p-1 text-xs font-bold">
                <button type="button" @click="tab = 'advance'" 
                        :class="tab === 'advance' ? 'bg-amber-500 text-white shadow-xs' : 'text-slate-400 hover:text-white'" 
                        class="px-3.5 py-1.5 rounded-lg transition cursor-pointer flex items-center gap-1.5">
                    <i data-lucide="credit-card" class="w-3.5 h-3.5"></i>
                    <span>{{ app()->getLocale() === 'ur' ? 'پیشگی خرچہ / ادھار' : 'Advance (Kharcha)' }}</span>
                </button>
                <button type="button" @click="tab = 'salary'" 
                        :class="tab === 'salary' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-400 hover:text-white'" 
                        class="px-3.5 py-1.5 rounded-lg transition cursor-pointer flex items-center gap-1.5">
                    <i data-lucide="banknote" class="w-3.5 h-3.5"></i>
                    <span>{{ app()->getLocale() === 'ur' ? 'تنخواہ کی ادائیگی' : 'Salary Payout' }}</span>
                </button>
                <button type="button" @click="tab = 'bonus_deduction'" 
                        :class="tab === 'bonus_deduction' ? 'bg-purple-600 text-white shadow-xs' : 'text-slate-400 hover:text-white'" 
                        class="px-3.5 py-1.5 rounded-lg transition cursor-pointer flex items-center gap-1.5">
                    <i data-lucide="scale" class="w-3.5 h-3.5"></i>
                    <span>{{ app()->getLocale() === 'ur' ? 'بونس یا کٹوتی' : 'Bonus / Deduction' }}</span>
                </button>
            </div>
        </div>

        <form method="POST" action="{{ route('employees.transactions.store', $employee->id) }}" class="p-6">
            @csrf

            <!-- Form Fields -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Transaction Type (Driven by Tab or Select) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        {{ app()->getLocale() === 'ur' ? 'اندراج کی قسم *' : 'Transaction Type *' }}
                    </label>
                    <select name="type" x-model="tab" class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                        <option value="advance_kharcha">{{ app()->getLocale() === 'ur' ? '💸 پیشگی خرچہ / ادھار (Advance)' : 'Advance (Kharcha)' }}</option>
                        <option value="salary_payment">{{ app()->getLocale() === 'ur' ? '💵 تنخواہ کی ادائیگی (Salary)' : 'Salary Payout' }}</option>
                        <option value="bonus">{{ app()->getLocale() === 'ur' ? '🎁 بونس / انعام (Bonus)' : 'Bonus' }}</option>
                        <option value="deduction">{{ app()->getLocale() === 'ur' ? '✂️ جرمانہ / کٹوتی (Deduction)' : 'Deduction' }}</option>
                    </select>
                </div>

                <!-- Amount -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        {{ app()->getLocale() === 'ur' ? 'رقم (PKR) *' : 'Amount (PKR) *' }}
                    </label>
                    <input type="number" step="10" min="1" name="amount" required placeholder="5000" 
                           class="w-full text-xs font-bold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition font-mono">
                </div>

                <!-- Date -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        {{ app()->getLocale() === 'ur' ? 'تاریخ *' : 'Date *' }}
                    </label>
                    <input type="date" name="transaction_date" value="{{ date('Y-m-d') }}" required 
                           class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                </div>

                <!-- Month / Year -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        {{ app()->getLocale() === 'ur' ? 'کس مہینے کا خرچہ / تنخواہ' : 'For Month / Period' }}
                    </label>
                    <input type="text" name="month_year" value="{{ now()->translatedFormat('F Y') }}" 
                           placeholder="{{ now()->format('F Y') }}"
                           class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                </div>

                <!-- Payment Method -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        {{ app()->getLocale() === 'ur' ? 'ادائیگی کا ذریعہ *' : 'Payment Method *' }}
                    </label>
                    <select name="payment_method" required class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                        <option value="cash">💵 {{ app()->getLocale() === 'ur' ? 'کیش دراز (Cash Drawer)' : 'Cash Drawer' }}</option>
                        <option value="bank_transfer">🏦 {{ app()->getLocale() === 'ur' ? 'بینک ٹرانسفر (Bank)' : 'Bank Transfer' }}</option>
                        <option value="cheque">📑 {{ app()->getLocale() === 'ur' ? 'چیک (Cheque)' : 'Cheque' }}</option>
                        <option value="other">{{ app()->getLocale() === 'ur' ? 'دیگر (Other)' : 'Other' }}</option>
                    </select>
                </div>

                <!-- Description / Purpose (Full detail as user requested) -->
                <div class="sm:col-span-2 lg:col-span-4">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        {{ app()->getLocale() === 'ur' ? 'خرچہ کی مکمل تفصیل / وجہ (Description) *' : 'Full Purpose / Expense Description *' }}
                    </label>
                    <input type="text" name="description" required 
                           :placeholder="tab === 'advance' ? '{{ app()->getLocale() === 'ur' ? 'مثلاً راشن خرچہ، ادویات کا خرچہ، ہنگامی گھریلو ضرورت' : 'e.g. Ration advance, medical emergency' }}' : (tab === 'salary' ? '{{ app()->getLocale() === 'ur' ? 'مثلاً ماہ اکتوبر کی مکمل تنخواہ' : 'e.g. October monthly salary' }}' : '{{ app()->getLocale() === 'ur' ? 'تفصیل و وجہ' : 'Description' }}')"
                           class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                </div>

                <!-- Submit Button -->
                <div class="sm:col-span-2 lg:col-span-1 flex items-end">
                    <button type="submit" 
                            class="w-full py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold transition shadow-sm shadow-teal-600/30 flex items-center justify-center gap-1.5 cursor-pointer">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <span>{{ app()->getLocale() === 'ur' ? 'کھاتے میں درج کریں' : 'Save Entry' }}</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Complete Transactions & Khata Ledger Table -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="p-5 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
            <div class="flex items-center gap-2.5">
                <i data-lucide="file-spreadsheet" class="w-5 h-5 text-teal-400"></i>
                <div>
                    <h3 class="text-sm font-bold">{{ app()->getLocale() === 'ur' ? 'ملازم کا مکمل کھاتہ لیجر' : 'Staff Financial Ledger & History' }}</h3>
                    <p class="text-[11px] text-slate-400">{{ app()->getLocale() === 'ur' ? 'تمام تنخواہوں، پیشگی خرچوں، کٹوتیوں اور ادائیگیوں کی تاریخ وار تفصیل' : 'Chronological audit of advances, salaries, and settlements' }}</p>
                </div>
            </div>
            <span class="text-xs font-bold bg-slate-800 text-slate-300 px-3 py-1 rounded-lg border border-slate-700">
                {{ $transactions->total() }} {{ app()->getLocale() === 'ur' ? 'کل اندراجات' : 'Entries' }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-start text-xs">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold uppercase tracking-wider text-[11px] border-b border-slate-200">
                        <th class="py-3 px-4 text-start">{{ app()->getLocale() === 'ur' ? 'تاریخ' : 'Date' }}</th>
                        <th class="py-3 px-4 text-start">{{ app()->getLocale() === 'ur' ? 'نمبر' : 'Tx #' }}</th>
                        <th class="py-3 px-4 text-start">{{ app()->getLocale() === 'ur' ? 'نوعیت' : 'Type' }}</th>
                        <th class="py-3 px-4 text-start">{{ app()->getLocale() === 'ur' ? 'مہینہ' : 'Month' }}</th>
                        <th class="py-3 px-4 text-start">{{ app()->getLocale() === 'ur' ? 'تفصیل و خرچہ کی وجہ' : 'Description / Reason' }}</th>
                        <th class="py-3 px-4 text-start">{{ app()->getLocale() === 'ur' ? 'ادائیگی کا ذریعہ' : 'Method' }}</th>
                        <th class="py-3 px-4 text-end">{{ app()->getLocale() === 'ur' ? 'رقم' : 'Amount' }}</th>
                        <th class="py-3 px-4 text-center">{{ app()->getLocale() === 'ur' ? 'درج کنندہ' : 'By' }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($transactions as $tx)
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <!-- Date -->
                        <td class="py-3 px-4 font-semibold text-slate-800 whitespace-nowrap">
                            {{ $tx->transaction_date->format('d M Y') }}
                        </td>

                        <!-- Number -->
                        <td class="py-3 px-4 font-mono text-[10px] text-slate-500 whitespace-nowrap">
                            {{ $tx->transaction_number }}
                        </td>

                        <!-- Type Badge -->
                        <td class="py-3 px-4 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-[10px] font-bold border {{ $tx->badge_class }}">
                                <span>{{ $tx->type_label }}</span>
                            </span>
                        </td>

                        <!-- Month -->
                        <td class="py-3 px-4 text-slate-600 font-medium whitespace-nowrap">
                            {{ $tx->month_year ?? '—' }}
                        </td>

                        <!-- Description -->
                        <td class="py-3 px-4">
                            <div class="font-semibold text-slate-900">{{ $tx->description ?? '—' }}</div>
                            @if($tx->notes)
                                <div class="text-[10px] text-slate-400 mt-0.5">{{ $tx->notes }}</div>
                            @endif
                        </td>

                        <!-- Payment Method -->
                        <td class="py-3 px-4 whitespace-nowrap capitalize text-slate-700">
                            {{ str_replace('_', ' ', $tx->payment_method) }}
                        </td>

                        <!-- Amount -->
                        <td class="py-3 px-4 text-end font-bold font-mono text-sm whitespace-nowrap">
                            @if($tx->type === 'salary_payment')
                                <span class="text-emerald-600">+ PKR {{ number_format($tx->amount, 0) }}</span>
                            @elseif($tx->type === 'advance_kharcha')
                                <span class="text-amber-600">- PKR {{ number_format($tx->amount, 0) }}</span>
                            @elseif($tx->type === 'bonus')
                                <span class="text-purple-600">+ PKR {{ number_format($tx->amount, 0) }}</span>
                            @else
                                <span class="text-rose-600">- PKR {{ number_format($tx->amount, 0) }}</span>
                            @endif
                        </td>

                        <!-- Recorded By -->
                        <td class="py-3 px-4 text-center text-slate-500 text-[11px] whitespace-nowrap">
                            {{ $tx->recorder->name ?? 'System' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center text-slate-400">
                            <i data-lucide="file-spreadsheet" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                            <p class="font-bold text-slate-600">{{ app()->getLocale() === 'ur' ? 'ابھی تک کوئی ٹرانزیکشن یا خرچہ درج نہیں ہوا' : 'No transactions recorded yet.' }}</p>
                            <p class="text-xs text-slate-400 mt-1">{{ app()->getLocale() === 'ur' ? 'اوپر دیے گئے فارم سے تنخواہ یا پیشگی خرچہ درج کریں۔' : 'Use the form above to record salary payout or advance.' }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transactions->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $transactions->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
