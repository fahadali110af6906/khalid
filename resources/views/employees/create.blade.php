@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Top Navigation Breadcrumb -->
    <div class="flex items-center justify-between">
        <a href="{{ route('employees.index', array_filter(['location_id' => request('location_id')])) }}" 
           class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-slate-800 transition">
            <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
            <span>{{ app()->getLocale() === 'ur' ? 'واپس فہرست ملازمین' : 'Back to Employees List' }}</span>
        </a>
    </div>

    <!-- Registration Card -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="p-6 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-teal-500/20 text-teal-300 rounded-xl border border-teal-400/20">
                    <i data-lucide="user-plus" class="w-5 h-5"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold">{{ app()->getLocale() === 'ur' ? 'نیا ملازم رجسٹر کریں' : 'Register New Employee / Staff' }}</h2>
                    <p class="text-xs text-slate-400">{{ app()->getLocale() === 'ur' ? 'شاپ، فیکٹری یا ویئر ہاؤس کے ملازم کی تفصیلات، تنخواہ اور رابطہ معلومات' : 'Record personal details, branch assignment, salary rate, and emergency contact' }}</p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('employees.store') }}" class="p-6 sm:p-8 space-y-6">
            @csrf

            <!-- Section 1: Basic & Location Info -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-teal-700 mb-3 flex items-center gap-1.5 border-b border-slate-100 pb-2">
                    <i data-lucide="id-card" class="w-4 h-4"></i>
                    <span>{{ app()->getLocale() === 'ur' ? 'بنیادی معلومات اور تعیناتی' : 'Personal & Location Details' }}</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Full Name -->
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'ملازم کا مکمل نام *' : 'Employee Full Name *' }}
                        </label>
                        <input type="text" name="name" value="{{ old('name') }}" required 
                               placeholder="{{ app()->getLocale() === 'ur' ? 'مثلاً محمد عمران' : 'e.g. Muhammad Imran' }}"
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                        @error('name') <span class="text-[11px] text-rose-500 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <!-- Location (Shop / Factory / Warehouse) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'تعیناتی لوکیشن (برانچ) *' : 'Assigned Location *' }}
                        </label>
                        <select name="location_id" required class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}" {{ (old('location_id', $defaultLocationId) == $loc->id) ? 'selected' : '' }}>
                                    {{ $loc->type === 'shop' ? '🏪' : ($loc->type === 'factory' ? '🏭' : '📦') }} {{ $loc->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('location_id') <span class="text-[11px] text-rose-500 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <!-- Designation / Role -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'عہدہ / کام کی نوعیت *' : 'Designation / Role *' }}
                        </label>
                        <input type="text" name="designation" value="{{ old('designation') }}" required 
                               placeholder="{{ app()->getLocale() === 'ur' ? 'مثلاً سیلزمین، کاریگر، ہیلپر، سپروائزر' : 'e.g. Salesman, Shoemaker Karigar, Helper' }}"
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                        @error('designation') <span class="text-[11px] text-rose-500 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <!-- Mobile / Phone -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'موبائل / فون نمبر' : 'Phone / Mobile' }}
                        </label>
                        <input type="text" name="phone" value="{{ old('phone') }}" 
                               placeholder="0300-1234567"
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    </div>

                    <!-- CNIC -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'قومی شناختی کارڈ نمبر (CNIC)' : 'CNIC Number' }}
                        </label>
                        <input type="text" name="cnic" value="{{ old('cnic') }}" 
                               placeholder="35201-1234567-1"
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    </div>

                    <!-- City -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'شہر' : 'City' }}
                        </label>
                        <input type="text" name="city" value="{{ old('city', 'Lahore') }}" 
                               placeholder="Lahore"
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    </div>

                    <!-- Address -->
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'رہائشی پتہ' : 'Residential Address' }}
                        </label>
                        <input type="text" name="address" value="{{ old('address') }}" 
                               placeholder="{{ app()->getLocale() === 'ur' ? 'مکان نمبر، گلی، محلہ، علاقہ' : 'House / Street / Area' }}"
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    </div>
                </div>
            </div>

            <!-- Section 2: Salary & Employment Terms -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-teal-700 mb-3 flex items-center gap-1.5 border-b border-slate-100 pb-2">
                    <i data-lucide="banknote" class="w-4 h-4"></i>
                    <span>{{ app()->getLocale() === 'ur' ? 'تنخواہ اور شرائطِ ملازمت' : 'Salary & Compensation Terms' }}</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Employment Type -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'تنخواہ کی نوعیت *' : 'Payment Type *' }}
                        </label>
                        <select name="employment_type" required class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                            <option value="monthly" {{ old('employment_type') === 'monthly' ? 'selected' : '' }}>{{ app()->getLocale() === 'ur' ? 'ماہانہ تنخواہ (Monthly Fixed)' : 'Monthly Fixed' }}</option>
                            <option value="daily" {{ old('employment_type') === 'daily' ? 'selected' : '' }}>{{ app()->getLocale() === 'ur' ? 'یومیہ دہاڑی (Daily Wage)' : 'Daily Wage' }}</option>
                            <option value="piece_rate" {{ old('employment_type') === 'piece_rate' ? 'selected' : '' }}>{{ app()->getLocale() === 'ur' ? 'پیس ریٹ / کاریگر (Piece Rate)' : 'Piece Rate' }}</option>
                        </select>
                    </div>

                    <!-- Monthly Base Salary -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'ماہانہ تنخواہ (PKR) *' : 'Monthly Salary (PKR) *' }}
                        </label>
                        <input type="number" step="100" min="0" name="monthly_salary" value="{{ old('monthly_salary', 35000) }}" required 
                               placeholder="35000"
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition font-mono">
                        @error('monthly_salary') <span class="text-[11px] text-rose-500 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <!-- Daily Rate -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'یومیہ ریٹ (اگر یومیہ ہو)' : 'Daily Rate (If Daily)' }}
                        </label>
                        <input type="number" step="50" min="0" name="daily_rate" value="{{ old('daily_rate') }}" 
                               placeholder="1500"
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition font-mono">
                    </div>

                    <!-- Joining Date -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'شمولیت کی تاریخ' : 'Joining Date' }}
                        </label>
                        <input type="date" name="joining_date" value="{{ old('joining_date', date('Y-m-d')) }}" 
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    </div>
                </div>
            </div>

            <!-- Section 3: Emergency Contact & Status -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-teal-700 mb-3 flex items-center gap-1.5 border-b border-slate-100 pb-2">
                    <i data-lucide="shield-alert" class="w-4 h-4"></i>
                    <span>{{ app()->getLocale() === 'ur' ? 'ہنگامی رابطہ اور اضافی نوٹس' : 'Emergency Contact & Status' }}</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Emergency Name -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'ہنگامی رابطے والے شخص کا نام' : 'Emergency Contact Person' }}
                        </label>
                        <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name') }}" 
                               placeholder="{{ app()->getLocale() === 'ur' ? 'بھائی / والد / عزیز کا نام' : 'Brother / Father / Kin' }}"
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    </div>

                    <!-- Emergency Phone -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'ہنگامی رابطہ نمبر' : 'Emergency Contact Phone' }}
                        </label>
                        <input type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}" 
                               placeholder="0321-7654321"
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'ملازمت اسٹیٹس *' : 'Employment Status *' }}
                        </label>
                        <select name="status" required class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                            <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>🟢 {{ app()->getLocale() === 'ur' ? 'فعال (Active)' : 'Active' }}</option>
                            <option value="on_leave" {{ old('status') === 'on_leave' ? 'selected' : '' }}>🟡 {{ app()->getLocale() === 'ur' ? 'چھٹی پر (On Leave)' : 'On Leave' }}</option>
                            <option value="resigned" {{ old('status') === 'resigned' ? 'selected' : '' }}>⚪ {{ app()->getLocale() === 'ur' ? 'مستعفی' : 'Resigned' }}</option>
                            <option value="terminated" {{ old('status') === 'terminated' ? 'selected' : '' }}>🔴 {{ app()->getLocale() === 'ur' ? 'ختم شدہ' : 'Terminated' }}</option>
                        </select>
                    </div>

                    <!-- Notes -->
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'اضافی نوٹس یا شرائط' : 'Additional Remarks / Notes' }}
                        </label>
                        <textarea name="notes" rows="2" 
                                  placeholder="{{ app()->getLocale() === 'ur' ? 'ملازم کے حوالے سے کوئی خاص ہدایات یا تفصیل...' : 'Any special notes, guarantors, or terms...' }}"
                                  class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('employees.index', array_filter(['location_id' => request('location_id')])) }}" 
                   class="px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold transition">
                    {{ app()->getLocale() === 'ur' ? 'منسوخ کریں' : 'Cancel' }}
                </a>

                <button type="submit" 
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold transition shadow-sm shadow-teal-600/30">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                    <span>{{ app()->getLocale() === 'ur' ? 'ملازم محفوظ کریں' : 'Save & Register Employee' }}</span>
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
