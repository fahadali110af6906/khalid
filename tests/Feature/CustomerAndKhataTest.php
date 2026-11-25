<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Accounting\CustomerKhataService;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class CustomerAndKhataTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $shop1Manager;
    protected User $shop2Manager;
    protected Location $shop1;
    protected Location $shop2;
    protected CustomerKhataService $khataService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->owner = User::where('email', 'khalid.owner@khalidtraders.pk')->firstOrFail();
        $this->shop1Manager = User::where('email', 'tariq.anarkali@khalidtraders.pk')->firstOrFail();
        $this->shop2Manager = User::where('email', 'irfan.shahalam@khalidtraders.pk')->firstOrFail();

        $this->shop1 = Location::where('code', 'LOC-SH01')->firstOrFail();
        $this->shop2 = Location::where('code', 'LOC-SH02')->firstOrFail();

        $this->khataService = app(CustomerKhataService::class);
    }

    /**
     * Test creating a customer and persistence in database.
     */
    public function test_can_create_customer_with_auto_generated_code(): void
    {
        $this->actingAs($this->shop1Manager);

        $response = $this->post(route('customers.store'), [
            'business_name' => 'Al-Rehman Footwear',
            'contact_person' => 'Haji Rehman',
            'phone' => '0300-1234567',
            'alternate_phone' => '0321-7654321',
            'city' => 'Lahore',
            'address' => 'Shop 12, Anarkali Bazaar',
            'tax_number' => '35202-1234567-1',
            'primary_location_id' => $this->shop1->id,
            'credit_limit' => 500000.00,
            'opening_balance' => 0.00,
        ]);

        $this->assertDatabaseHas('customers', [
            'business_name' => 'Al-Rehman Footwear',
            'contact_person' => 'Haji Rehman',
            'phone' => '03001234567',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->shop1Manager->id,
            'is_active' => true,
        ]);

        $customer = Customer::where('business_name', 'Al-Rehman Footwear')->firstOrFail();
        $this->assertStringStartsWith('CUST-', $customer->customer_code);
        $response->assertRedirect(route('customers.show', $customer));
    }

    /**
     * Test server-side required-field validation for customer creation.
     */
    public function test_customer_creation_requires_mandatory_fields(): void
    {
        $this->actingAs($this->shop1Manager);

        $response = $this->post(route('customers.store'), [
            'business_name' => '',
            'contact_person' => '',
            'phone' => '',
            'primary_location_id' => '',
        ]);

        $response->assertSessionHasErrors(['business_name', 'contact_person', 'phone', 'primary_location_id']);
    }

    /**
     * Test duplicate customer code is strictly rejected.
     */
    public function test_duplicate_customer_code_is_rejected(): void
    {
        $this->actingAs($this->owner);

        Customer::create([
            'customer_code' => 'CUST-EXISTING',
            'business_name' => 'Existing Shop',
            'contact_person' => 'Person A',
            'phone' => '03001112233',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $response = $this->post(route('customers.store'), [
            'customer_code' => 'CUST-EXISTING',
            'business_name' => 'Another Shop',
            'contact_person' => 'Person B',
            'phone' => '03004445566',
            'primary_location_id' => $this->shop1->id,
        ]);

        $response->assertSessionHasErrors('customer_code');
    }

    /**
     * Test customer search, location filtering, and active/inactive status toggle.
     */
    public function test_customer_search_and_status_toggle(): void
    {
        $customer = Customer::create([
            'customer_code' => 'CUST-SEARCH-01',
            'business_name' => 'Bismillah Shoes Multan',
            'contact_person' => 'Khurram Shehzad',
            'phone' => '03009998877',
            'city' => 'Multan',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->owner);

        // Search by city
        $responseSearch = $this->get(route('customers.index', ['search' => 'Multan']));
        $responseSearch->assertStatus(200);
        $responseSearch->assertSee('Bismillah Shoes Multan');

        // Toggle status to inactive
        $responseToggle = $this->post(route('customers.toggle-status', $customer));
        $responseToggle->assertRedirect(route('customers.show', $customer));

        $customer->refresh();
        $this->assertFalse($customer->is_active);

        // Filter active only -> customer code should not be listed in table
        $responseActive = $this->get(route('customers.index', ['status' => 'active']));
        $responseActive->assertDontSee('CUST-SEARCH-01');

        // Filter inactive -> customer is listed
        $responseInactive = $this->get(route('customers.index', ['status' => 'inactive']));
        $responseInactive->assertSee('CUST-SEARCH-01');
    }

    /**
     * Test Zero-IDOR: Shop manager cannot view, edit, or toggle customers of other locations.
     */
    public function test_cross_location_customer_access_is_forbidden(): void
    {
        // Customer registered at Shop 2 (Shah Alam)
        $shop2Customer = Customer::create([
            'customer_code' => 'CUST-SH02-001',
            'business_name' => 'Shah Alam Wholesaler',
            'contact_person' => 'Irfan Bhai',
            'phone' => '03112223344',
            'primary_location_id' => $this->shop2->id,
            'created_by' => $this->shop2Manager->id,
            'is_active' => true,
        ]);

        // Shop 1 Manager attempts to access Shop 2 customer -> 403 Forbidden!
        $this->actingAs($this->shop1Manager);

        $viewResponse = $this->get(route('customers.show', $shop2Customer));
        $viewResponse->assertStatus(403);

        $editResponse = $this->get(route('customers.edit', $shop2Customer));
        $editResponse->assertStatus(403);

        $toggleResponse = $this->post(route('customers.toggle-status', $shop2Customer));
        $toggleResponse->assertStatus(403);

        $ledgerResponse = $this->get(route('customers.ledger', $shop2Customer));
        $ledgerResponse->assertStatus(403);

        // Shop 1 Manager index query must not leak Shop 2 customers
        $listResponse = $this->get(route('customers.index'));
        $listResponse->assertStatus(200);
        $listResponse->assertDontSee('Shah Alam Wholesaler');

        // Owner can access Shop 2 customer -> 200 OK
        $ownerResponse = $this->actingAs($this->owner)->get(route('customers.show', $shop2Customer));
        $ownerResponse->assertStatus(200);
        $ownerResponse->assertSee('Shah Alam Wholesaler');
    }

    /**
     * Test customer registration with non-zero opening balance creates auditable ledger entry.
     */
    public function test_customer_creation_with_opening_balance_creates_ledger_entry(): void
    {
        $this->actingAs($this->shop1Manager);

        $this->post(route('customers.store'), [
            'customer_code' => 'CUST-OPENING-01',
            'business_name' => 'Khyber Footwear Peshawar',
            'contact_person' => 'Khan Sahib',
            'phone' => '03339876543',
            'primary_location_id' => $this->shop1->id,
            'opening_balance' => 75000.00,
        ]);

        $customer = Customer::where('customer_code', 'CUST-OPENING-01')->firstOrFail();

        $this->assertDatabaseHas('customer_ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => 'opening_balance',
            'debit_amount' => 75000.00,
            'credit_amount' => 0.00,
        ]);

        $summary = $this->khataService->calculateBalance($customer);
        $this->assertEquals('75000.00', $summary['closing_balance']);
        $this->assertEquals('receivable', $summary['balance_status']);
    }

    /**
     * Test Khata balance calculation with multiple debit and credit entries.
     */
    public function test_khata_balance_calculation_and_sign_convention(): void
    {
        $customer = Customer::create([
            'customer_code' => 'CUST-KHATA-01',
            'business_name' => 'Faisalabad Leather Emporium',
            'contact_person' => 'Chaudhry Akram',
            'phone' => '03017778899',
            'primary_location_id' => $this->shop1->id,
            'opening_balance' => 0.00,
            'created_by' => $this->owner->id,
        ]);

        // Entry 1: Wholesale Sale on Credit (Debit = PKR 200,000)
        $this->khataService->recordEntry(
            customer: $customer,
            locationId: $this->shop1->id,
            entryType: 'sale_invoice',
            debitAmount: 200000.00,
            creditAmount: 0.00,
            entryDate: '2026-10-01',
            user: $this->owner,
            description: 'Invoice #INV-001 (50 cartons)',
            referenceType: 'Invoice',
            referenceId: 101,
            referenceNumber: 'INV-001'
        );

        $summary1 = $this->khataService->calculateBalance($customer);
        $this->assertEquals('200000.00', $summary1['closing_balance']);
        $this->assertEquals('receivable', $summary1['balance_status']);

        // Entry 2: Customer Partial Payment received in Cash (Credit = PKR 120,000)
        $this->khataService->recordEntry(
            customer: $customer,
            locationId: $this->shop1->id,
            entryType: 'payment_received',
            debitAmount: 0.00,
            creditAmount: 120000.00,
            entryDate: '2026-10-05',
            user: $this->owner,
            description: 'Cash payment received (Receipt #RCP-001)',
            referenceType: 'Payment',
            referenceId: 201,
            referenceNumber: 'RCP-001'
        );

        $summary2 = $this->khataService->calculateBalance($customer);
        // 200,000 - 120,000 = 80,000
        $this->assertEquals('80000.00', $summary2['closing_balance']);
        $this->assertEquals('receivable', $summary2['balance_status']);

        // Entry 3: Goods Return Credit Note (Credit = PKR 30,000)
        $this->khataService->recordEntry(
            customer: $customer,
            locationId: $this->shop1->id,
            entryType: 'sale_return',
            debitAmount: 0.00,
            creditAmount: 30000.00,
            entryDate: '2026-10-10',
            user: $this->owner,
            description: 'Credit Note for 5 defective cartons',
            referenceType: 'Return',
            referenceId: 301,
            referenceNumber: 'CRN-001'
        );

        $summary3 = $this->khataService->calculateBalance($customer);
        // 80,000 - 30,000 = 50,000
        $this->assertEquals('50000.00', $summary3['closing_balance']);
        $this->assertEquals('receivable', $summary3['balance_status']);

        // Entry 4: Customer pays PKR 70,000 (overshoots remaining 50,000 -> 20,000 advance credit)
        $this->khataService->recordEntry(
            customer: $customer,
            locationId: $this->shop1->id,
            entryType: 'payment_received',
            debitAmount: 0.00,
            creditAmount: 70000.00,
            entryDate: '2026-10-15',
            user: $this->owner,
            description: 'Advance bank payment received',
            referenceType: 'Payment',
            referenceId: 202,
            referenceNumber: 'RCP-002'
        );

        $summary4 = $this->khataService->calculateBalance($customer);
        // 50,000 - 70,000 = -20,000 (Advance)
        $this->assertEquals('-20000.00', $summary4['closing_balance']);
        $this->assertEquals('payable', $summary4['balance_status']);
    }

    /**
     * Test date-filtered Khata statement captures prior movements into opening balance.
     */
    public function test_khata_date_filtering_and_period_opening_balance(): void
    {
        $customer = Customer::create([
            'customer_code' => 'CUST-DATE-01',
            'business_name' => 'Date Test Store',
            'contact_person' => 'Bilal',
            'phone' => '03001239999',
            'primary_location_id' => $this->shop1->id,
            'opening_balance' => 10000.00, // Base opening
            'created_by' => $this->owner->id,
        ]);

        // Prior entry in Sept (Debit 50,000)
        $this->khataService->recordEntry(
            customer: $customer,
            locationId: $this->shop1->id,
            entryType: 'sale_invoice',
            debitAmount: 50000.00,
            creditAmount: 0.00,
            entryDate: '2026-09-15',
            user: $this->owner,
            description: 'September Invoice'
        );

        // Prior entry in Sept (Credit 20,000)
        $this->khataService->recordEntry(
            customer: $customer,
            locationId: $this->shop1->id,
            entryType: 'payment_received',
            debitAmount: 0.00,
            creditAmount: 20000.00,
            entryDate: '2026-09-25',
            user: $this->owner,
            description: 'September Payment'
        );

        // Current entry in Oct (Debit 40,000)
        $this->khataService->recordEntry(
            customer: $customer,
            locationId: $this->shop1->id,
            entryType: 'sale_invoice',
            debitAmount: 40000.00,
            creditAmount: 0.00,
            entryDate: '2026-10-05',
            user: $this->owner,
            description: 'October Invoice'
        );

        // Query Khata for October (2026-10-01 to 2026-10-31)
        // Expected Period Opening = 10,000 (base) + 50,000 (prior debit) - 20,000 (prior credit) = 40,000
        // Expected Period Debit = 40,000
        // Expected Period Credit = 0
        // Expected Closing = 40,000 + 40,000 = 80,000
        $octSummary = $this->khataService->calculateBalance(
            customer: $customer,
            locationId: null,
            fromDate: '2026-10-01',
            toDate: '2026-10-31'
        );

        $this->assertEquals('40000.00', $octSummary['period_opening_balance']);
        $this->assertEquals('40000.00', $octSummary['total_debit']);
        $this->assertEquals('0.00', $octSummary['total_credit']);
        $this->assertEquals('80000.00', $octSummary['closing_balance']);
        $this->assertEquals(1, $octSummary['entries_count']);
    }

    /**
     * Test prevention of duplicate ledger postings for the same commercial reference.
     */
    public function test_prevention_of_duplicate_ledger_postings(): void
    {
        $customer = Customer::create([
            'customer_code' => 'CUST-DUP-01',
            'business_name' => 'Duplicate Test Store',
            'contact_person' => 'Nadeem',
            'phone' => '03005556677',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $this->khataService->recordEntry(
            customer: $customer,
            locationId: $this->shop1->id,
            entryType: 'sale_invoice',
            debitAmount: 10000.00,
            creditAmount: 0.00,
            entryDate: '2026-10-20',
            user: $this->owner,
            referenceType: 'SalesInvoice',
            referenceId: 999
        );

        // Attempting to post the same invoice reference again must throw RuntimeException
        $this->expectException(RuntimeException::class);
        $this->khataService->recordEntry(
            customer: $customer,
            locationId: $this->shop1->id,
            entryType: 'sale_invoice',
            debitAmount: 10000.00,
            creditAmount: 0.00,
            entryDate: '2026-10-20',
            user: $this->owner,
            referenceType: 'SalesInvoice',
            referenceId: 999
        );
    }

    /**
     * Test posted ledger entries are strictly immutable (cannot be updated or deleted).
     */
    public function test_customer_ledger_entries_are_immutable(): void
    {
        $customer = Customer::create([
            'customer_code' => 'CUST-IMMUTABLE-01',
            'business_name' => 'Immutability Check Store',
            'contact_person' => 'Aslam',
            'phone' => '03003332211',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $entry = $this->khataService->recordEntry(
            customer: $customer,
            locationId: $this->shop1->id,
            entryType: 'sale_invoice',
            debitAmount: 50000.00,
            creditAmount: 0.00,
            entryDate: '2026-10-22',
            user: $this->owner,
            description: 'Immutable record'
        );

        // Attempting to modify entry via Eloquent throws RuntimeException
        try {
            $entry->debit_amount = 40000.00;
            $entry->save();
            $this->fail("Expected RuntimeException when updating ledger entry.");
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // Attempting to delete entry throws RuntimeException
        try {
            $entry->delete();
            $this->fail("Expected RuntimeException when deleting ledger entry.");
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        }
    }

    /**
     * Test viewing sales foundation page does not modify stock or customer balances.
     */
    public function test_viewing_sales_foundation_does_not_change_stock_or_balances(): void
    {
        $category = ProductCategory::firstOrFail();
        $pairUnit = UnitOfMeasure::firstOrFail();

        $product = Product::create([
            'article_code' => 'ART-NOCHANGE',
            'name' => 'Non Mutating Shoe',
            'category_id' => $category->id,
            'base_unit_id' => $pairUnit->id,
            'carton_pack_qty' => 12,
            'default_wholesale_rate' => 2500,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'ART-NOCHANGE-BLK-40',
            'size' => '40',
            'color' => 'Black',
        ]);

        // Add 100 pairs of stock
        app(InventoryService::class)->addStock(
            $this->shop1->id,
            $variant->id,
            100.0,
            1500.0,
            'opening_balance',
            $this->owner
        );

        $customer = Customer::create([
            'customer_code' => 'CUST-NOCHANGE-01',
            'business_name' => 'No Mutation Party',
            'contact_person' => 'Zubair',
            'phone' => '03008887766',
            'primary_location_id' => $this->shop1->id,
            'opening_balance' => 50000.00,
            'created_by' => $this->owner->id,
        ]);

        $stockBefore = (float) StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $variant->id)
            ->value('pairs_quantity');

        $khataBefore = $this->khataService->calculateBalance($customer)['closing_balance'];

        // Visit sales foundation page
        $response = $this->actingAs($this->owner)->get(route('sales.index'));
        $response->assertStatus(200);

        $stockAfter = (float) StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $variant->id)
            ->value('pairs_quantity');

        $khataAfter = $this->khataService->calculateBalance($customer)['closing_balance'];

        $this->assertEquals($stockBefore, $stockAfter);
        $this->assertEquals($khataBefore, $khataAfter);
        $this->assertEquals(100.0, $stockAfter);
        $this->assertEquals('50000.00', $khataAfter);
    }

    /**
     * Test Debit and Credit mutual exclusivity and boundary validations.
     */
    public function test_debit_and_credit_mutual_exclusivity(): void
    {
        $customer = Customer::create([
            'customer_code' => 'CUST-EXCL-01',
            'business_name' => 'Exclusivity Test Store',
            'contact_person' => 'Rashid',
            'phone' => '03004443322',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        // 1. Valid debit-only entry
        $debitEntry = $this->khataService->recordEntry(
            customer: $customer,
            locationId: $this->shop1->id,
            entryType: 'sale_invoice',
            debitAmount: 5000.00,
            creditAmount: 0.00,
            entryDate: '2026-10-22',
            user: $this->owner,
            description: 'Valid debit'
        );
        $this->assertEquals('5000.00', $debitEntry->debit_amount);
        $this->assertEquals('0.00', $debitEntry->credit_amount);

        // 2. Valid credit-only entry
        $creditEntry = $this->khataService->recordEntry(
            customer: $customer,
            locationId: $this->shop1->id,
            entryType: 'payment_received',
            debitAmount: 0.00,
            creditAmount: 3000.00,
            entryDate: '2026-10-22',
            user: $this->owner,
            description: 'Valid credit'
        );
        $this->assertEquals('0.00', $creditEntry->debit_amount);
        $this->assertEquals('3000.00', $creditEntry->credit_amount);

        // 3. Both amounts positive — rejected
        try {
            $this->khataService->recordEntry(
                customer: $customer,
                locationId: $this->shop1->id,
                entryType: 'sale_invoice',
                debitAmount: 1000.00,
                creditAmount: 500.00,
                entryDate: '2026-10-22',
                user: $this->owner
            );
            $this->fail("Expected InvalidArgumentException when both debit and credit are positive.");
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('simultaneously', $e->getMessage());
        }

        // 4. Both amounts zero — rejected
        try {
            $this->khataService->recordEntry(
                customer: $customer,
                locationId: $this->shop1->id,
                entryType: 'sale_invoice',
                debitAmount: 0.00,
                creditAmount: 0.00,
                entryDate: '2026-10-22',
                user: $this->owner
            );
            $this->fail("Expected InvalidArgumentException when both debit and credit are zero.");
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('non-zero', $e->getMessage());
        }

        // 5. Negative debit — rejected
        try {
            $this->khataService->recordEntry(
                customer: $customer,
                locationId: $this->shop1->id,
                entryType: 'sale_invoice',
                debitAmount: -100.00,
                creditAmount: 0.00,
                entryDate: '2026-10-22',
                user: $this->owner
            );
            $this->fail("Expected InvalidArgumentException for negative debit.");
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('non-negative', $e->getMessage());
        }

        // 6. Negative credit — rejected
        try {
            $this->khataService->recordEntry(
                customer: $customer,
                locationId: $this->shop1->id,
                entryType: 'payment_received',
                debitAmount: 0.00,
                creditAmount: -200.00,
                entryDate: '2026-10-22',
                user: $this->owner
            );
            $this->fail("Expected InvalidArgumentException for negative credit.");
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('non-negative', $e->getMessage());
        }
    }

    /**
     * Test database-level composite unique constraint on commercial reference.
     */
    public function test_database_level_duplicate_reference_protection(): void
    {
        $customerA = Customer::create([
            'customer_code' => 'CUST-REF-A',
            'business_name' => 'Party Alpha',
            'contact_person' => 'Alpha',
            'phone' => '03001119999',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $customerB = Customer::create([
            'customer_code' => 'CUST-REF-B',
            'business_name' => 'Party Beta',
            'contact_person' => 'Beta',
            'phone' => '03002228888',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        // Customer A: Post Invoice #5001
        $this->khataService->recordEntry(
            customer: $customerA,
            locationId: $this->shop1->id,
            entryType: 'sale_invoice',
            debitAmount: 25000.00,
            creditAmount: 0.00,
            entryDate: '2026-10-22',
            user: $this->owner,
            referenceType: 'SalesInvoice',
            referenceId: 5001,
            referenceNumber: 'INV-5001'
        );

        // Attempting to post the same invoice #5001 for Customer A must fail
        try {
            $this->khataService->recordEntry(
                customer: $customerA,
                locationId: $this->shop1->id,
                entryType: 'sale_invoice',
                debitAmount: 25000.00,
                creditAmount: 0.00,
                entryDate: '2026-10-22',
                user: $this->owner,
                referenceType: 'SalesInvoice',
                referenceId: 5001,
                referenceNumber: 'INV-5001'
            );
            $this->fail("Expected RuntimeException on duplicate reference for same customer.");
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Duplicate ledger entry', $e->getMessage());
        }

        // Distinct customer (Customer B) CAN have reference SalesInvoice #5001 without collision
        $entryB = $this->khataService->recordEntry(
            customer: $customerB,
            locationId: $this->shop1->id,
            entryType: 'sale_invoice',
            debitAmount: 30000.00,
            creditAmount: 0.00,
            entryDate: '2026-10-22',
            user: $this->owner,
            referenceType: 'SalesInvoice',
            referenceId: 5001,
            referenceNumber: 'INV-5001'
        );
        $this->assertEquals($customerB->id, $entryB->customer_id);

        // Distinct reference for same customer (Customer A with Invoice #5002) is permitted
        $entryA2 = $this->khataService->recordEntry(
            customer: $customerA,
            locationId: $this->shop1->id,
            entryType: 'sale_invoice',
            debitAmount: 15000.00,
            creditAmount: 0.00,
            entryDate: '2026-10-22',
            user: $this->owner,
            referenceType: 'SalesInvoice',
            referenceId: 5002,
            referenceNumber: 'INV-5002'
        );
        $this->assertEquals(5002, $entryA2->reference_id);

        // Multiple entries without references (null reference_type and null reference_id) can coexist
        $unreferencedEntry1 = $this->khataService->recordEntry(
            customer: $customerA,
            locationId: $this->shop1->id,
            entryType: 'adjustment_debit',
            debitAmount: 500.00,
            creditAmount: 0.00,
            entryDate: '2026-10-22',
            user: $this->owner,
            description: 'Manual adjustment 1'
        );

        $unreferencedEntry2 = $this->khataService->recordEntry(
            customer: $customerA,
            locationId: $this->shop1->id,
            entryType: 'adjustment_credit',
            debitAmount: 0.00,
            creditAmount: 200.00,
            entryDate: '2026-10-22',
            user: $this->owner,
            description: 'Manual adjustment 2'
        );

        $this->assertNull($unreferencedEntry1->reference_type);
        $this->assertNull($unreferencedEntry2->reference_type);
        $this->assertNotEquals($unreferencedEntry1->id, $unreferencedEntry2->id);
    }
}
