@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Top Navigation Breadcrumb -->
    <div class="flex items-center justify-between">
        <a href="{{ route('employees.show', ['employee' => $employee->id, 'location_id' => $employee->location_id]) }}" 
           class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-slate-800 transition">
            <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
            <span>{{ app()->getLocale() === 'ur' ? 'واپس ملازم پروفائل و کھاتہ' : 'Back to Employee Profile' }}</span>
        </a>
    </div>

    <!-- Edit Card -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="p-6 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-teal-500/20 text-teal-300 rounded-xl border border-teal-400/20">
                    <i data-lucide="edit-3" class="w-5 h-5"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold">{{ app()->getLocale() === 'ur' ? 'ملازم کی معلومات میں ترمیم' : 'Edit Employee Details' }}</h2>
                    <p class="text-xs text-slate-400">{{ $employee->name }} ({{ $employee->employee_code }})</p>
                </div>
            </div>
            <span class="text-xs font-mono bg-teal-500/20 text-teal-300 border border-teal-400/30 px-3 py-1 rounded-lg">
                {{ $employee->employee_code }}
            </span>
        </div>

        <form method="POST" action="{{ route('employees.update', $employee->id) }}" class="p-6 sm:p-8 space-y-6">
            @csrf
            @method('PUT')

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
                        <input type="text" name="name" value="{{ old('name', $employee->name) }}" required 
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                        @error('name') <span class="text-[11px] text-rose-500 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <!-- Location -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'تعیناتی لوکیشن (برانچ) *' : 'Assigned Location *' }}
                        </label>
                        <select name="location_id" required class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}" {{ (old('location_id', $employee->location_id) == $loc->id) ? 'selected' : '' }}>
                                    {{ $loc->type === 'shop' ? '🏪' : ($loc->type === 'factory' ? '🏭' : '📦') }} {{ $loc->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('location_id') <span class="text-[11px] text-rose-500 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <!-- Designation -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'عہدہ / کام کی نوعیت *' : 'Designation / Role *' }}
                        </label>
                        <input type="text" name="designation" value="{{ old('designation', $employee->designation) }}" required 
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                        @error('designation') <span class="text-[11px] text-rose-500 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <!-- Phone -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'موبائل / فون نمبر' : 'Phone / Mobile' }}
                        </label>
                        <input type="text" name="phone" value="{{ old('phone', $employee->phone) }}" 
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    </div>

                    <!-- CNIC -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'قومی شناختی کارڈ نمبر (CNIC)' : 'CNIC Number' }}
                        </label>
                        <input type="text" name="cnic" value="{{ old('cnic', $employee->cnic) }}" 
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    </div>

                    <!-- City -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'شہر' : 'City' }}
                        </label>
                        <input type="text" name="city" value="{{ old('city', $employee->city) }}" 
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    </div>

                    <!-- Address -->
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'رہائشی پتہ' : 'Residential Address' }}
                        </label>
                        <input type="text" name="address" value="{{ old('address', $employee->address) }}" 
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    </div>
                </div>
            </div>

            <!-- Section 2: Salary Terms -->
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
                            <option value="monthly" {{ old('employment_type', $employee->employment_type) === 'monthly' ? 'selected' : '' }}>{{ app()->getLocale() === 'ur' ? 'ماہانہ تنخواہ (Monthly Fixed)' : 'Monthly Fixed' }}</option>
                            <option value="daily" {{ old('employment_type', $employee->employment_type) === 'daily' ? 'selected' : '' }}>{{ app()->getLocale() === 'ur' ? 'یومیہ دہاڑی (Daily Wage)' : 'Daily Wage' }}</option>
                            <option value="piece_rate" {{ old('employment_type', $employee->employment_type) === 'piece_rate' ? 'selected' : '' }}>{{ app()->getLocale() === 'ur' ? 'پیس ریٹ / کاریگر (Piece Rate)' : 'Piece Rate' }}</option>
                        </select>
                    </div>

                    <!-- Monthly Base Salary -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'ماہانہ تنخواہ (PKR) *' : 'Monthly Salary (PKR) *' }}
                        </label>
                        <input type="number" step="100" min="0" name="monthly_salary" value="{{ old('monthly_salary', $employee->monthly_salary) }}" required 
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition font-mono">
                    </div>

                    <!-- Daily Rate -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'یومیہ ریٹ' : 'Daily Rate' }}
                        </label>
                        <input type="number" step="50" min="0" name="daily_rate" value="{{ old('daily_rate', $employee->daily_rate) }}" 
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition font-mono">
                    </div>

                    <!-- Joining Date -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'شمولیت کی تاریخ' : 'Joining Date' }}
                        </label>
                        <input type="date" name="joining_date" value="{{ old('joining_date', $employee->joining_date?->format('Y-m-d')) }}" 
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    </div>
                </div>
            </div>

            <!-- Section 3: Emergency & Status -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-teal-700 mb-3 flex items-center gap-1.5 border-b border-slate-100 pb-2">
                    <i data-lucide="shield-alert" class="w-4 h-4"></i>
                    <span>{{ app()->getLocale() === 'ur' ? 'ہنگامی رابطہ اور اسٹیٹس' : 'Emergency Contact & Status' }}</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Emergency Name -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'ہنگامی رابطہ شخص' : 'Emergency Contact Person' }}
                        </label>
                        <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $employee->emergency_contact_name) }}" 
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    </div>

                    <!-- Emergency Phone -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'ہنگامی رابطہ نمبر' : 'Emergency Phone' }}
                        </label>
                        <input type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $employee->emergency_contact_phone) }}" 
                               class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'ملازمت اسٹیٹس *' : 'Employment Status *' }}
                        </label>
                        <select name="status" required class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                            <option value="active" {{ old('status', $employee->status) === 'active' ? 'selected' : '' }}>🟢 {{ app()->getLocale() === 'ur' ? 'فعال (Active)' : 'Active' }}</option>
                            <option value="on_leave" {{ old('status', $employee->status) === 'on_leave' ? 'selected' : '' }}>🟡 {{ app()->getLocale() === 'ur' ? 'چھٹی پر (On Leave)' : 'On Leave' }}</option>
                            <option value="resigned" {{ old('status', $employee->status) === 'resigned' ? 'selected' : '' }}>⚪ {{ app()->getLocale() === 'ur' ? 'مستعفی' : 'Resigned' }}</option>
                            <option value="terminated" {{ old('status', $employee->status) === 'terminated' ? 'selected' : '' }}>🔴 {{ app()->getLocale() === 'ur' ? 'ختم شدہ' : 'Terminated' }}</option>
                        </select>
                    </div>

                    <!-- Notes -->
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            {{ app()->getLocale() === 'ur' ? 'اضافی نوٹس' : 'Notes / Remarks' }}
                        </label>
                        <textarea name="notes" rows="2" 
                                  class="w-full text-xs font-semibold rounded-xl border border-slate-300 p-2.5 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">{{ old('notes', $employee->notes) }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                <!-- Delete Form -->
                <button type="button" onclick="if(confirm('کیا آپ واقعی اس ملازم کو حذف کرنا چاہتے ہیں؟')) { document.getElementById('delete-employee-form').submit(); }" 
                        class="text-xs font-bold text-rose-600 hover:text-rose-800 transition flex items-center gap-1.5">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    <span>{{ app()->getLocale() === 'ur' ? 'ملازم کو حذف کریں' : 'Delete Employee' }}</span>
                </button>

                <div class="flex items-center gap-3">
                    <a href="{{ route('employees.show', ['employee' => $employee->id, 'location_id' => $employee->location_id]) }}" 
                       class="px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold transition">
                        {{ app()->getLocale() === 'ur' ? 'منسوخ کریں' : 'Cancel' }}
                    </a>

                    <button type="submit" 
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold transition shadow-sm shadow-teal-600/30">
                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                        <span>{{ app()->getLocale() === 'ur' ? 'تبدیلیاں محفوظ کریں' : 'Update Details' }}</span>
                    </button>
                </div>
            </div>
        </form>

        <form id="delete-employee-form" method="POST" action="{{ route('employees.destroy', $employee->id) }}" style="display: none;">
            @csrf
            @method('DELETE')
        </form>
    </div>

</div>
@endsection
