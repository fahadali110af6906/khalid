@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <!-- Top Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-teal-500 to-indigo-600 flex items-center justify-center text-white shadow-md shadow-teal-500/20">
                    <i data-lucide="users-2" class="w-5 h-5"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">
                        {{ app()->getLocale() === 'ur' ? 'ملازمین و تنخواہ کا انتظام' : 'Staff & Employee Management' }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ app()->getLocale() === 'ur' ? 'شاپس، فیکٹریوں اور ویئر ہاؤس کے ملازمین، تنخواہوں اور پیشگی خرچے کا مکمل نظام' : 'Complete directory of staff, base salaries, monthly advance expenses, and payouts' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('employees.create', array_filter(['location_id' => $currentLocId])) }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold transition shadow-sm shadow-teal-600/30">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                <span>{{ app()->getLocale() === 'ur' ? 'نیا ملازم شامل کریں' : 'Add New Employee' }}</span>
            </a>
        </div>
    </div>

    <!-- 4 KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Employees -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                    {{ app()->getLocale() === 'ur' ? 'کل ملازمین' : 'Total Employees' }}
                </span>
                <div class="p-2 bg-teal-50 text-teal-600 rounded-lg">
                    <i data-lucide="users" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-slate-900 mt-2">{{ $totalEmployees }}</div>
            <div class="text-xs text-slate-500 mt-1">
                {{ app()->getLocale() === 'ur' ? 'ملازمین زیرِ نگرانی' : 'Staff in current view' }}
            </div>
        </div>

        <!-- Monthly Salary Budget -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                    {{ app()->getLocale() === 'ur' ? 'ماہانہ تنخواہ کا بجٹ' : 'Monthly Salary Budget' }}
                </span>
                <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                    <i data-lucide="banknote" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-indigo-700 mt-2">PKR {{ number_format($totalMonthlySalaryBudget, 0) }}</div>
            <div class="text-xs text-slate-500 mt-1">
                {{ app()->getLocale() === 'ur' ? 'فعال ملازمین کی کل تنخواہ' : 'Active payroll base sum' }}
            </div>
        </div>

        <!-- Current Month Advances / Kharcha -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                    {{ app()->getLocale() === 'ur' ? 'رواں ماہ کا پیشگی خرچہ' : 'Month Advances / Kharcha' }}
                </span>
                <div class="p-2 bg-amber-50 text-amber-600 rounded-lg">
                    <i data-lucide="credit-card" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-amber-600 mt-2">PKR {{ number_format($currentMonthAdvances, 0) }}</div>
            <div class="text-xs text-slate-500 mt-1">
                {{ app()->getLocale() === 'ur' ? 'پیشگی ادا شدہ ادھار' : 'Advances taken this month' }}
            </div>
        </div>

        <!-- Current Month Salaries Paid -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                    {{ app()->getLocale() === 'ur' ? 'رواں ماہ ادا شدہ تنخواہیں' : 'Salaries Paid (Month)' }}
                </span>
                <div class="p-2 bg-emerald-50 text-emerald-600 rounded-lg">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-emerald-600 mt-2">PKR {{ number_format($currentMonthSalariesPaid, 0) }}</div>
            <div class="text-xs text-slate-500 mt-1">
                {{ app()->getLocale() === 'ur' ? 'تنخواہوں کی منتقلی' : 'Total disbursements' }}
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('employees.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Search -->
            <div class="relative">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute start-3 top-3"></i>
                <input type="text" name="search" value="{{ request('search') }}" 
                       placeholder="{{ app()->getLocale() === 'ur' ? 'نام، فون یا شناختی کارڈ تلاش کریں...' : 'Search name, phone, CNIC...' }}"
                       class="w-full text-xs font-semibold rounded-xl border border-slate-300 bg-slate-50 ps-9 pe-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
            </div>

            <!-- Location Selector (Strict for Non-owners) -->
            @if($isOwner)
            <div>
                <select name="location_id" onchange="this.form.submit()" class="w-full text-xs font-semibold rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    <option value="all" {{ ($currentLocId === 'all' || !$currentLocId) ? 'selected' : '' }}>🏢 {{ __('erp.all_locations') }} (All Branches)</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ $currentLocId == $loc->id ? 'selected' : '' }}>
                            {{ $loc->type === 'shop' ? '🏪' : ($loc->type === 'factory' ? '🏭' : '📦') }} {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @else
            <div>
                <div class="px-3 py-2.5 rounded-xl bg-slate-100 border border-slate-200 text-xs font-bold text-slate-700 flex items-center gap-2">
                    <span>{{ $currentLocation?->type === 'shop' ? '🏪' : ($currentLocation?->type === 'factory' ? '🏭' : '📦') }}</span>
                    <span class="truncate">{{ $currentLocation?->name ?? 'Assigned Location' }}</span>
                </div>
                <input type="hidden" name="location_id" value="{{ $currentLocId }}">
            </div>
            @endif

            <!-- Designation Filter -->
            <div>
                <select name="designation" onchange="this.form.submit()" class="w-full text-xs font-semibold rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    <option value="">{{ app()->getLocale() === 'ur' ? 'تمام عہدے (Designations)' : 'All Designations' }}</option>
                    @foreach($designations as $desig)
                        <option value="{{ $desig }}" {{ request('designation') === $desig ? 'selected' : '' }}>{{ $desig }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Actions / Status -->
            <div class="flex items-center gap-2">
                <select name="status" onchange="this.form.submit()" class="w-full text-xs font-semibold rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    <option value="">{{ app()->getLocale() === 'ur' ? 'تمام اسٹیٹس' : 'All Statuses' }}</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>🟢 {{ app()->getLocale() === 'ur' ? 'فعال (Active)' : 'Active' }}</option>
                    <option value="on_leave" {{ request('status') === 'on_leave' ? 'selected' : '' }}>🟡 {{ app()->getLocale() === 'ur' ? 'چھٹی پر (On Leave)' : 'On Leave' }}</option>
                    <option value="resigned" {{ request('status') === 'resigned' ? 'selected' : '' }}>⚪ {{ app()->getLocale() === 'ur' ? 'مستعفی' : 'Resigned' }}</option>
                    <option value="terminated" {{ request('status') === 'terminated' ? 'selected' : '' }}>🔴 {{ app()->getLocale() === 'ur' ? 'ختم شدہ' : 'Terminated' }}</option>
                </select>
                @if(request()->anyFilled(['search', 'designation', 'status']) || (request('location_id') && request('location_id') !== 'all'))
                    <a href="{{ route('employees.index', array_filter(['location_id' => $isOwner ? null : $currentLocId])) }}" 
                       class="px-3 py-2.5 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold transition flex items-center justify-center" title="Reset Filters">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Employees List Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-start text-xs">
                <thead>
                    <tr class="bg-slate-900 text-white font-bold uppercase tracking-wider text-[11px] border-b border-slate-800">
                        <th class="py-3 px-4 text-start">{{ app()->getLocale() === 'ur' ? 'کوڈ و ملازم کا نام' : 'Employee' }}</th>
                        <th class="py-3 px-4 text-start">{{ app()->getLocale() === 'ur' ? 'شاپ / فیکٹری' : 'Location' }}</th>
                        <th class="py-3 px-4 text-start">{{ app()->getLocale() === 'ur' ? 'عہدہ' : 'Designation' }}</th>
                        <th class="py-3 px-4 text-start">{{ app()->getLocale() === 'ur' ? 'رابطہ نمبر و پتہ' : 'Phone & Address' }}</th>
                        <th class="py-3 px-4 text-end">{{ app()->getLocale() === 'ur' ? 'ماہانہ تنخواہ' : 'Monthly Salary' }}</th>
                        <th class="py-3 px-4 text-end">{{ app()->getLocale() === 'ur' ? 'پیشگی خرچہ (زیرِ کٹوتی)' : 'Pending Advance' }}</th>
                        <th class="py-3 px-4 text-center">{{ app()->getLocale() === 'ur' ? 'اسٹیٹس' : 'Status' }}</th>
                        <th class="py-3 px-4 text-center">{{ app()->getLocale() === 'ur' ? 'کھاتہ / ایکشن' : 'Actions' }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($employees as $emp)
                    <tr class="hover:bg-slate-50/90 transition-colors">
                        <!-- Name & Code -->
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-teal-500/10 border border-teal-500/20 text-teal-700 font-black text-xs flex items-center justify-center">
                                    {{ mb_substr($emp->name, 0, 2) }}
                                </div>
                                <div>
                                    <a href="{{ route('employees.show', ['employee' => $emp->id, 'location_id' => $emp->location_id]) }}" class="font-bold text-slate-900 hover:text-teal-600 transition">
                                        {{ $emp->name }}
                                    </a>
                                    <div class="font-mono text-[10px] text-slate-400">{{ $emp->employee_code }}</div>
                                </div>
                            </div>
                        </td>

                        <!-- Location -->
                        <td class="py-3 px-4">
                            @php
                                $locIcon = match($emp->location->type ?? 'shop') {
                                    'shop' => '🏪',
                                    'factory' => '🏭',
                                    'warehouse' => '📦',
                                    default => '📍',
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                <span>{{ $locIcon }}</span>
                                <span>{{ $emp->location->name ?? 'N/A' }}</span>
                            </span>
                        </td>

                        <!-- Designation -->
                        <td class="py-3 px-4">
                            <span class="font-bold text-slate-800">{{ $emp->designation }}</span>
                            <div class="text-[10px] text-slate-400 capitalize">{{ $emp->employment_type }}</div>
                        </td>

                        <!-- Phone & Address -->
                        <td class="py-3 px-4">
                            <div class="font-semibold text-slate-800">{{ $emp->phone ?? '—' }}</div>
                            <div class="text-[10px] text-slate-400 truncate max-w-xs">{{ $emp->address ? ($emp->address . ($emp->city ? ', ' . $emp->city : '')) : '—' }}</div>
                        </td>

                        <!-- Monthly Salary -->
                        <td class="py-3 px-4 text-end font-bold text-slate-900 font-mono">
                            PKR {{ number_format($emp->monthly_salary, 0) }}
                        </td>

                        <!-- Pending Advance Kharcha -->
                        <td class="py-3 px-4 text-end font-mono">
                            @php $adv = $emp->pending_advance_balance; @endphp
                            @if($adv > 0)
                                <span class="font-bold text-amber-600">PKR {{ number_format($adv, 0) }}</span>
                                <div class="text-[9px] text-amber-500 font-sans">{{ app()->getLocale() === 'ur' ? 'پیشگی ادھار' : 'Kharcha advance' }}</div>
                            @else
                                <span class="text-slate-400">PKR 0</span>
                            @endif
                        </td>

                        <!-- Status -->
                        <td class="py-3 px-4 text-center">
                            @if($emp->status === 'active')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>{{ app()->getLocale() === 'ur' ? 'فعال' : 'Active' }}</span>
                                </span>
                            @elseif($emp->status === 'on_leave')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    <span>{{ app()->getLocale() === 'ur' ? 'چھٹی' : 'On Leave' }}</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                                    <span>{{ ucfirst($emp->status) }}</span>
                                </span>
                            @endif
                        </td>

                        <!-- Actions -->
                        <td class="py-3 px-4 text-center">
                            <div class="inline-flex items-center gap-1.5">
                                <a href="{{ route('employees.show', ['employee' => $emp->id, 'location_id' => $emp->location_id]) }}" 
                                   class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[11px] font-bold bg-teal-50 hover:bg-teal-100 text-teal-700 border border-teal-200 transition" title="View Profile & Khata">
                                    <i data-lucide="book-open" class="w-3.5 h-3.5"></i>
                                    <span>{{ app()->getLocale() === 'ur' ? 'کھاتہ' : 'Khata' }}</span>
                                </a>

                                <a href="{{ route('employees.edit', $emp->id) }}" 
                                   class="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition" title="Edit">
                                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center text-slate-400">
                            <i data-lucide="users-2" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                            <p class="font-bold text-slate-600">{{ app()->getLocale() === 'ur' ? 'کوئی ملازم موجود نہیں ہے' : 'No employees found in this view.' }}</p>
                            <p class="text-xs text-slate-400 mt-1">{{ app()->getLocale() === 'ur' ? 'نیا ملازم شامل کرنے کے لیے اوپر دیے گئے بٹن پر کلک کریں۔' : 'Click the button above to add the first staff member.' }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($employees->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $employees->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
