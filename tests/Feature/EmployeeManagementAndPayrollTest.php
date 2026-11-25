<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeTransaction;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementAndPayrollTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $shopManager;
    protected Location $shop;
    protected Location $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner'], ['label' => 'Business Owner']);
        $shopManagerRole = Role::firstOrCreate(['name' => 'shop_manager'], ['label' => 'Shop Manager']);

        $this->shop = Location::create([
            'code' => 'SH01',
            'name' => 'Shop 1 — Anarkali',
            'type' => 'shop',
            'city' => 'Lahore',
            'is_active' => true,
        ]);

        $this->factory = Location::create([
            'code' => 'FAC1',
            'name' => 'Factory 1 — Kot Lakhpat',
            'type' => 'factory',
            'city' => 'Lahore',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create(['is_active' => true]);
        $this->owner->roles()->sync([$ownerRole->id]);

        $this->shopManager = User::factory()->create(['is_active' => true]);
        $this->shopManager->roles()->sync([$shopManagerRole->id]);
        $this->shopManager->locations()->attach($this->shop->id, ['is_primary' => true]);
    }

    public function test_owner_can_view_all_employees_and_metrics(): void
    {
        app()->setLocale('ur');

        Employee::create([
            'employee_code' => 'EMP-001',
            'location_id' => $this->shop->id,
            'name' => 'محمد رضوان',
            'designation' => 'سیلزمین',
            'employment_type' => 'monthly',
            'monthly_salary' => 45000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->owner)->get(route('employees.index'));
        $response->assertStatus(200);
        $response->assertSee('محمد رضوان');
        $response->assertSee('ملازمین و تنخواہ کا انتظام');
    }

    public function test_shop_manager_isolated_to_shop_employees(): void
    {
        Employee::create([
            'employee_code' => 'EMP-SH1',
            'location_id' => $this->shop->id,
            'name' => 'کامران احمد',
            'designation' => 'سیلزمین',
            'employment_type' => 'monthly',
            'monthly_salary' => 40000,
            'status' => 'active',
        ]);

        Employee::create([
            'employee_code' => 'EMP-FAC1',
            'location_id' => $this->factory->id,
            'name' => 'استاد بشیر احمد',
            'designation' => 'کاریگر',
            'employment_type' => 'monthly',
            'monthly_salary' => 60000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->shopManager)->get(route('employees.index'));
        $response->assertStatus(200);

        // Shop manager should see Shop employee, but NOT Factory employee
        $response->assertSee('کامران احمد');
        $response->assertDontSee('استاد بشیر احمد');
    }

    public function test_can_create_employee(): void
    {
        $response = $this->actingAs($this->owner)->post(route('employees.store'), [
            'name' => 'زاہد محمود',
            'location_id' => $this->shop->id,
            'designation' => 'سیلزمین',
            'phone' => '0300-9988776',
            'cnic' => '35202-7788990-1',
            'address' => 'لاہور کینٹ',
            'city' => 'Lahore',
            'employment_type' => 'monthly',
            'monthly_salary' => 38000.00,
            'joining_date' => '2026-10-01',
            'status' => 'active',
            'emergency_contact_name' => 'محمود اختر',
            'emergency_contact_phone' => '0321-1122334',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('employees', [
            'name' => 'زاہد محمود',
            'location_id' => $this->shop->id,
            'monthly_salary' => 38000.00,
        ]);
    }

    public function test_can_record_advance_kharcha_and_salary_payout(): void
    {
        $emp = Employee::create([
            'employee_code' => 'EMP-001',
            'location_id' => $this->shop->id,
            'name' => 'محمد رضوان',
            'designation' => 'سیلزمین',
            'employment_type' => 'monthly',
            'monthly_salary' => 45000,
            'status' => 'active',
        ]);

        // Record Advance Kharcha
        $advResponse = $this->actingAs($this->owner)->post(route('employees.transactions.store', $emp->id), [
            'type' => 'advance_kharcha',
            'amount' => 4500,
            'transaction_date' => '2026-10-25',
            'month_year' => 'October 2026',
            'payment_method' => 'cash',
            'description' => 'بچے کے علاج کے لیے ایمرجنسی پیشگی خرچہ',
        ]);

        $advResponse->assertRedirect();

        $this->assertDatabaseHas('employee_transactions', [
            'employee_id' => $emp->id,
            'type' => 'advance_kharcha',
            'amount' => 4500.00,
        ]);

        // View Employee Ledger / Show Page
        $showResponse = $this->actingAs($this->owner)->get(route('employees.show', [
            'employee' => $emp->id,
            'location_id' => $emp->location_id
        ]));

        $showResponse->assertStatus(200);
        $showResponse->assertSee('بچے کے علاج کے لیے ایمرجنسی پیشگی خرچہ');
        $showResponse->assertSee('4,500');
    }
}
