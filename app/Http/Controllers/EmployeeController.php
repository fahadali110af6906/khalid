<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeTransaction;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    /**
     * Display a listing of employees with KPI metrics and location filtering.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $isOwner = $user && $user->isOwner();

        // 1. Resolve authorized locations
        if ($isOwner) {
            $locations = Location::where('is_active', true)->where('type', '!=', 'transit')->orderBy('type')->orderBy('name')->get();
        } else {
            $locations = $user ? $user->locations()->where('is_active', true)->where('type', '!=', 'transit')->orderBy('name')->get() : collect();
            if ($locations->isEmpty() && $user && $user->defaultLocation) {
                $locations = collect([$user->defaultLocation]);
            }
        }

        $locParam = $request->get('location_id') ?? $request->get('location');
        $currentLocId = ($locParam instanceof Location) ? $locParam->id : $locParam;

        // Strict isolation for non-owner: cannot view other branches
        if (!$isOwner && $user) {
            $userLocIds = $locations->pluck('id')->all();
            if (!$currentLocId || $currentLocId === 'all' || !in_array((int)$currentLocId, $userLocIds)) {
                $currentLocId = $user->default_location_id ?? ($userLocIds[0] ?? null);
            }
        }

        // 2. Base Query
        $query = Employee::with(['location', 'transactions']);

        if ($currentLocId && $currentLocId !== 'all') {
            $query->where('location_id', $currentLocId);
        } elseif (!$isOwner && $user) {
            $query->whereIn('location_id', $locations->pluck('id'));
        }

        // Filters
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('employee_code', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('cnic', 'like', "%{$s}%")
                  ->orWhere('designation', 'like', "%{$s}%");
            });
        }

        if ($request->filled('designation')) {
            $query->where('designation', $request->designation);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 3. Compute KPIs for the filtered scope
        $kpiQuery = clone $query;
        $allScopedEmployees = $kpiQuery->get();
        $totalEmployees = $allScopedEmployees->count();
        $totalMonthlySalaryBudget = (float) $allScopedEmployees->where('status', 'active')->sum('monthly_salary');

        $currentMonth = now()->format('Y-m');
        $scopedEmployeeIds = $allScopedEmployees->pluck('id');
        
        $currentMonthAdvances = (float) EmployeeTransaction::whereIn('employee_id', $scopedEmployeeIds)
            ->where('type', 'advance_kharcha')
            ->where('transaction_date', 'like', "{$currentMonth}%")
            ->sum('amount');

        $currentMonthSalariesPaid = (float) EmployeeTransaction::whereIn('employee_id', $scopedEmployeeIds)
            ->where('type', 'salary_payment')
            ->where('transaction_date', 'like', "{$currentMonth}%")
            ->sum('amount');

        $employees = $query->orderBy('name')->paginate(15)->withQueryString();

        // Unique designations for filter dropdown
        $designations = Employee::distinct()->whereNotNull('designation')->pluck('designation');

        $currentLocation = ($currentLocId && $currentLocId !== 'all') ? Location::find($currentLocId) : null;

        return view('employees.index', compact(
            'employees',
            'locations',
            'currentLocId',
            'currentLocation',
            'totalEmployees',
            'totalMonthlySalaryBudget',
            'currentMonthAdvances',
            'currentMonthSalariesPaid',
            'designations',
            'isOwner'
        ));
    }

    /**
     * Show form to create a new employee.
     */
    public function create(Request $request): View
    {
        $user = auth()->user();
        $isOwner = $user && $user->isOwner();

        if ($isOwner) {
            $locations = Location::where('is_active', true)->where('type', '!=', 'transit')->orderBy('type')->orderBy('name')->get();
        } else {
            $locations = $user ? $user->locations()->where('is_active', true)->where('type', '!=', 'transit')->get() : collect();
            if ($locations->isEmpty() && $user && $user->defaultLocation) {
                $locations = collect([$user->defaultLocation]);
            }
        }

        $defaultLocationId = $request->get('location_id') ?? ($locations->first()?->id ?? null);

        return view('employees.create', compact('locations', 'defaultLocationId', 'isOwner'));
    }

    /**
     * Store a newly created employee.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $isOwner = $user && $user->isOwner();

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'location_id' => 'required|exists:locations,id',
            'designation' => 'required|string|max:100',
            'phone' => 'nullable|string|max:50',
            'cnic' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:60',
            'employment_type' => 'required|in:monthly,daily,piece_rate',
            'monthly_salary' => 'required|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'joining_date' => 'nullable|date',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'status' => 'required|in:active,on_leave,resigned,terminated',
            'notes' => 'nullable|string',
        ]);

        // Security check for non-owners: ensure they can only assign to their allowed locations
        if (!$isOwner) {
            $allowedLocs = $user->locations->pluck('id')->all();
            if (!in_array((int)$validated['location_id'], $allowedLocs)) {
                abort(403, 'Unauthorized to create employees for this location.');
            }
        }

        $validated['employee_code'] = Employee::generateCode((int)$validated['location_id']);
        $validated['created_by'] = $user->id;

        $employee = Employee::create($validated);

        return redirect()->route('employees.show', ['employee' => $employee->id, 'location_id' => $employee->location_id])
            ->with('success', "ملازم {$employee->name} ({$employee->employee_code}) کامیابی سے رجسٹر کر دیا گیا ہے۔");
    }

    /**
     * Display employee profile, contact details, and complete financial khata/ledger.
     */
    public function show(Employee $employee, Request $request): View
    {
        $this->authorizeLocationAccess($employee->location_id);

        $employee->load(['location', 'transactions.recorder', 'creator']);

        $transactions = $employee->transactions()->paginate(20);

        // Khata totals
        $totalPaid = (float) $employee->transactions()->where('type', 'salary_payment')->sum('amount');
        $totalAdvances = (float) $employee->transactions()->where('type', 'advance_kharcha')->sum('amount');
        $totalBonuses = (float) $employee->transactions()->where('type', 'bonus')->sum('amount');
        $totalDeductions = (float) $employee->transactions()->where('type', 'deduction')->sum('amount');
        $pendingAdvanceBalance = (float) ($totalAdvances - $totalDeductions);

        return view('employees.show', compact(
            'employee',
            'transactions',
            'totalPaid',
            'totalAdvances',
            'totalBonuses',
            'totalDeductions',
            'pendingAdvanceBalance'
        ));
    }

    /**
     * Show form to edit employee.
     */
    public function edit(Employee $employee): View
    {
        $this->authorizeLocationAccess($employee->location_id);

        $user = auth()->user();
        $isOwner = $user && $user->isOwner();

        if ($isOwner) {
            $locations = Location::where('is_active', true)->where('type', '!=', 'transit')->orderBy('type')->orderBy('name')->get();
        } else {
            $locations = $user->locations()->where('is_active', true)->where('type', '!=', 'transit')->get();
        }

        return view('employees.edit', compact('employee', 'locations', 'isOwner'));
    }

    /**
     * Update employee.
     */
    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeLocationAccess($employee->location_id);

        $user = auth()->user();
        $isOwner = $user && $user->isOwner();

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'location_id' => 'required|exists:locations,id',
            'designation' => 'required|string|max:100',
            'phone' => 'nullable|string|max:50',
            'cnic' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:60',
            'employment_type' => 'required|in:monthly,daily,piece_rate',
            'monthly_salary' => 'required|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'joining_date' => 'nullable|date',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'status' => 'required|in:active,on_leave,resigned,terminated',
            'notes' => 'nullable|string',
        ]);

        if (!$isOwner) {
            $allowedLocs = $user->locations->pluck('id')->all();
            if (!in_array((int)$validated['location_id'], $allowedLocs)) {
                abort(403, 'Unauthorized to move employees to this location.');
            }
        }

        $employee->update($validated);

        return redirect()->route('employees.show', ['employee' => $employee->id, 'location_id' => $employee->location_id])
            ->with('success', "ملازم {$employee->name} کی معلومات کامیابی کے ساتھ اپ ڈیٹ کر دی گئی ہیں۔");
    }

    /**
     * Soft delete an employee.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorizeLocationAccess($employee->location_id);

        $name = $employee->name;
        $locId = $employee->location_id;
        $employee->delete();

        return redirect()->route('employees.index', ['location_id' => $locId])
            ->with('success', "ملازم {$name} کو کامیابی سے سسٹم سے غیر فعال/حذف کر دیا گیا ہے۔");
    }

    /**
     * Store an employee salary payout, advance kharcha, bonus, or deduction transaction.
     */
    public function storeTransaction(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeLocationAccess($employee->location_id);

        $validated = $request->validate([
            'type' => 'required|in:salary_payment,advance_kharcha,bonus,deduction',
            'amount' => 'required|numeric|min:1',
            'transaction_date' => 'required|date',
            'month_year' => 'nullable|string|max:30',
            'payment_method' => 'required|in:cash,bank_transfer,cheque,other',
            'description' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $txNumber = EmployeeTransaction::generateTransactionNumber($validated['type']);

        $tx = EmployeeTransaction::create([
            'transaction_number' => $txNumber,
            'employee_id' => $employee->id,
            'location_id' => $employee->location_id,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'transaction_date' => $validated['transaction_date'],
            'month_year' => $validated['month_year'] ?? now()->format('F Y'),
            'payment_method' => $validated['payment_method'],
            'description' => $validated['description'] ?? ($validated['type'] === 'advance_kharcha' ? 'پیشگی خرچہ / ادھار' : 'تنخواہ کی ادائیگی'),
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => auth()->id(),
        ]);

        $label = $tx->type_label;
        $formattedAmount = number_format($tx->amount, 0);

        return redirect()->route('employees.show', ['employee' => $employee->id, 'location_id' => $employee->location_id])
            ->with('success', "{$label} (PKR {$formattedAmount}) ملازم {$employee->name} کے کھاتے میں کامیابی کے ساتھ درج کر دی گئی ہے۔");
    }

    /**
     * Location access check helper.
     */
    protected function authorizeLocationAccess(int $locationId): void
    {
        $user = auth()->user();
        if (!$user) abort(401);
        if ($user->isOwner()) return;

        $allowedLocs = $user->locations->pluck('id')->all();
        if (!in_array($locationId, $allowedLocs)) {
            abort(403, 'Unauthorized access to this location\'s employees.');
        }
    }
}
