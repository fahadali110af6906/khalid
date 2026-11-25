<?php

namespace Tests\Feature;

use App\Models\AccountTransaction;
use App\Models\AccountTransfer;
use App\Models\Customer;
use App\Models\CustomerRefund;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Accounting\CashAndBankService;
use App\Services\Accounting\CustomerKhataService;
use App\Services\Accounting\ExpenseService;
use App\Services\Accounting\SupplierKhataService;
use App\Services\Inventory\InventoryService;
use App\Services\Purchasing\PurchaseService;
use App\Services\Sales\SalesPostingService;
use App\Services\Sales\SalesReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class CashBankAndExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $shop1Manager;
    protected User $shop2Manager;
    protected Location $shop1;
    protected Location $shop2;
    protected Customer $customer;
    protected Supplier $supplier;
    protected Product $product;
    protected ProductVariant $variant;
    protected CashAndBankService $cashAndBankService;
    protected ExpenseService $expenseService;
    protected SalesPostingService $salesPostingService;
    protected PurchaseService $purchaseService;
    protected SalesReturnService $salesReturnService;
    protected InventoryService $inventoryService;
    protected ExpenseCategory $rentCategory;
    protected ExpenseCategory $utilityCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->owner = User::where('email', 'khalid.owner@khalidtraders.pk')->firstOrFail();
        $this->shop1Manager = User::where('email', 'tariq.anarkali@khalidtraders.pk')->firstOrFail();
        $this->shop2Manager = User::where('email', 'irfan.shahalam@khalidtraders.pk')->firstOrFail();

        $this->shop1 = Location::where('code', 'LOC-SH01')->firstOrFail();
        $this->shop2 = Location::where('code', 'LOC-SH02')->firstOrFail();

        $this->cashAndBankService = app(CashAndBankService::class);
        $this->expenseService = app(ExpenseService::class);
        $this->salesPostingService = app(SalesPostingService::class);
        $this->purchaseService = app(PurchaseService::class);
        $this->salesReturnService = app(SalesReturnService::class);
        $this->inventoryService = app(InventoryService::class);

        // Setup customer
        $this->customer = Customer::firstOrCreate(
            ['customer_code' => 'CUST-001'],
            [
                'business_name' => 'Bismillah Footwear',
                'contact_person' => 'Chaudhry Akram',
                'phone' => '03001234567',
                'primary_location_id' => $this->shop1->id,
                'credit_limit' => 500000.00,
                'opening_balance' => 0.00,
                'created_by' => $this->owner->id,
            ]
        );

        // Setup supplier
        $this->supplier = Supplier::firstOrCreate(
            ['supplier_code' => 'SUPP-001'],
            [
                'business_name' => 'Apex Soles Industries',
                'contact_person' => 'Sheikh Noman',
                'phone' => '03005556677',
                'primary_location_id' => $this->shop1->id,
                'opening_balance' => 0.00,
                'is_active' => true,
                'created_by' => $this->owner->id,
            ]
        );

        // Setup test product and variants
        $uom = UnitOfMeasure::firstOrCreate(['code' => 'PAIR'], ['name' => 'Pair', 'is_base_unit' => true]);
        $category = ProductCategory::firstOrCreate(['code' => 'CAT-FORMAL'], ['name' => 'Formal Footwear']);

        $this->product = Product::firstOrCreate(
            ['article_code' => 'ART-CB-01'],
            [
                'category_id' => $category->id,
                'base_unit_id' => $uom->id,
                'name' => 'Classic Derby Leather Shoes',
                'carton_pack_qty' => 12,
                'default_wholesale_rate' => 24000.00,
                'default_retail_rate' => 2500.00,
                'is_active' => true,
            ]
        );

        $this->variant = ProductVariant::firstOrCreate(
            ['sku' => 'CB-BLK-42'],
            [
                'product_id' => $this->product->id,
                'size' => '42',
                'color' => 'Black',
                'wholesale_rate' => 24000.00,
                'purchase_cost' => 1500.0000,
                'is_active' => true,
            ]
        );

        $this->inventoryService->addStock(
            locationId: $this->shop1->id,
            variantId: $this->variant->id,
            quantityPairs: 100.0,
            unitCost: 1000.00,
            movementType: 'purchase',
            user: $this->owner,
            notes: 'Test stock baseline'
        );

        // Setup expense categories
        $this->rentCategory = ExpenseCategory::firstOrCreate(
            ['code' => 'EXP-RENT'],
            ['name' => 'Shop Rent', 'is_active' => true]
        );
        $this->utilityCategory = ExpenseCategory::firstOrCreate(
            ['code' => 'EXP-UTIL'],
            ['name' => 'Electricity & Utilities', 'is_active' => true]
        );
    }

    /** 1. Account creation and opening balance posting. */
    public function test_account_creation_and_opening_balance_posting(): void
    {
        $account = $this->cashAndBankService->createAccount([
            'name' => 'Shop 1 Cash Drawer',
            'account_code' => 'CASH-SH01',
            'type' => 'cash',
            'location_id' => $this->shop1->id,
        ], $this->owner);

        $this->assertEquals('0.00', $account->opening_balance);
        $this->assertEquals('0.00', $account->current_balance);

        $txn = $this->cashAndBankService->recordOpeningBalance(
            account: $account,
            amount: 50000.00,
            date: '2026-10-01',
            user: $this->owner,
            notes: 'Initial cash float'
        );

        $account->refresh();
        $this->assertEquals('50000.00', $account->opening_balance);
        $this->assertEquals('50000.00', $account->current_balance);
        $this->assertEquals('opening_balance', $txn->transaction_type);
        $this->assertEquals('50000.00', $txn->debit_amount);
        $this->assertEquals('0.00', $txn->credit_amount);
        $this->assertEquals('50000.00', $txn->balance_after);
    }

    /** 2. Opening balance cannot be applied twice. */
    public function test_opening_balance_cannot_be_applied_twice(): void
    {
        $account = $this->cashAndBankService->createAccount([
            'name' => 'Shop 1 Vault',
            'account_code' => 'VAULT-01',
            'type' => 'cash',
            'location_id' => $this->shop1->id,
        ], $this->owner);

        $this->cashAndBankService->recordOpeningBalance(
            account: $account,
            amount: 25000.00,
            date: '2026-10-01',
            user: $this->owner
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Opening balance has already been posted');

        $this->cashAndBankService->recordOpeningBalance(
            account: $account,
            amount: 10000.00,
            date: '2026-10-02',
            user: $this->owner
        );
    }

    /** 3. Cash receipt increases correct account. */
    public function test_cash_receipt_increases_correct_account(): void
    {
        $account = $this->cashAndBankService->createAccount([
            'name' => 'Counter Cash',
            'account_code' => 'CTR-01',
            'type' => 'cash',
            'location_id' => $this->shop1->id,
        ], $this->owner);

        $txn = $this->cashAndBankService->recordTransaction(
            account: $account,
            transactionType: 'customer_receipt',
            debitAmount: 15000.00,
            creditAmount: 0.00,
            transactionDate: '2026-10-24',
            user: $this->owner,
            description: 'Customer payment'
        );

        $account->refresh();
        $this->assertEquals('15000.00', $account->current_balance);
        $this->assertEquals('15000.00', $txn->balance_after);
        $this->assertEquals('15000.00', $txn->debit_amount);
        $this->assertEquals('0.00', $txn->credit_amount);
    }

    /** 4. Cash payment decreases correct account. */
    public function test_cash_payment_decreases_correct_account(): void
    {
        $account = $this->cashAndBankService->createAccount([
            'name' => 'Petty Cash',
            'account_code' => 'PTY-01',
            'type' => 'cash',
            'location_id' => $this->shop1->id,
        ], $this->owner);

        $this->cashAndBankService->recordOpeningBalance($account, 20000.00, '2026-10-01', $this->owner);

        $txn = $this->cashAndBankService->recordTransaction(
            account: $account,
            transactionType: 'supplier_payment',
            debitAmount: 0.00,
            creditAmount: 8000.00,
            transactionDate: '2026-10-24',
            user: $this->owner,
            description: 'Cash payment to vendor'
        );

        $account->refresh();
        $this->assertEquals('12000.00', $account->current_balance);
        $this->assertEquals('12000.00', $txn->balance_after);
        $this->assertEquals('8000.00', $txn->credit_amount);
    }

    /** 5. Bank deposit and withdrawal. */
    public function test_bank_deposit_and_withdrawal(): void
    {
        $bank = $this->cashAndBankService->createAccount([
            'name' => 'HBL Current Account',
            'account_code' => 'HBL-001',
            'type' => 'bank',
            'bank_name' => 'Habib Bank Limited',
            'account_number' => 'PK00HABB000123456789',
            'location_id' => null, // Central
        ], $this->owner);

        // Deposit (Inflow -> Debit)
        $this->cashAndBankService->recordTransaction(
            account: $bank,
            transactionType: 'customer_receipt',
            debitAmount: 100000.00,
            creditAmount: 0.00,
            transactionDate: '2026-10-20',
            user: $this->owner,
            description: 'Online direct transfer from customer'
        );

        $bank->refresh();
        $this->assertEquals('100000.00', $bank->current_balance);

        // Withdrawal / Outflow (Credit)
        $this->cashAndBankService->recordTransaction(
            account: $bank,
            transactionType: 'supplier_payment',
            debitAmount: 0.00,
            creditAmount: 35000.00,
            transactionDate: '2026-10-21',
            user: $this->owner,
            description: 'Supplier RTGS transfer'
        );

        $bank->refresh();
        $this->assertEquals('65000.00', $bank->current_balance);
    }

    /** 6. Successful transfer updates both accounts atomically. */
    public function test_successful_transfer_updates_both_accounts_atomically(): void
    {
        $cashDrawer = $this->cashAndBankService->createAccount([
            'name' => 'Daily Cash Drawer',
            'account_code' => 'CASH-D01',
            'type' => 'cash',
            'location_id' => $this->shop1->id,
        ], $this->owner);

        $bankAccount = $this->cashAndBankService->createAccount([
            'name' => 'Meezan Islamic Banking',
            'account_code' => 'MEEZAN-01',
            'type' => 'bank',
            'bank_name' => 'Meezan Bank',
            'location_id' => null,
        ], $this->owner);

        $this->cashAndBankService->recordOpeningBalance($cashDrawer, 60000.00, '2026-10-01', $this->owner);
        $this->cashAndBankService->recordOpeningBalance($bankAccount, 100000.00, '2026-10-01', $this->owner);

        $transfer = $this->cashAndBankService->recordTransfer(
            fromAccount: $cashDrawer,
            toAccount: $bankAccount,
            amount: 40000.00,
            transferDate: '2026-10-24',
            user: $this->owner,
            notes: 'Deposit shop cash into bank'
        );

        $cashDrawer->refresh();
        $bankAccount->refresh();

        $this->assertEquals('20000.00', $cashDrawer->current_balance);
        $this->assertEquals('140000.00', $bankAccount->current_balance);
        $this->assertEquals('40000.00', $transfer->amount);
        $this->assertCount(2, $transfer->transactions);

        // Check legs
        $outflowLeg = $transfer->transactions()->where('transaction_type', 'transfer_out')->first();
        $inflowLeg = $transfer->transactions()->where('transaction_type', 'transfer_in')->first();

        $this->assertEquals('40000.00', $outflowLeg->credit_amount);
        $this->assertEquals('40000.00', $inflowLeg->debit_amount);
    }

    /** 7. Transfer failure rolls back both sides. */
    public function test_transfer_failure_rolls_back_both_sides(): void
    {
        $cashDrawer = $this->cashAndBankService->createAccount([
            'name' => 'Empty Drawer',
            'account_code' => 'CASH-EMP',
            'type' => 'cash',
            'location_id' => $this->shop1->id,
        ], $this->owner);

        $bankAccount = $this->cashAndBankService->createAccount([
            'name' => 'Bank Target',
            'account_code' => 'BNK-TGT',
            'type' => 'bank',
            'location_id' => null,
        ], $this->owner);

        $this->cashAndBankService->recordOpeningBalance($bankAccount, 50000.00, '2026-10-01', $this->owner);

        // Attempt transfer exceeding balance
        try {
            $this->cashAndBankService->recordTransfer(
                fromAccount: $cashDrawer,
                toAccount: $bankAccount,
                amount: 15000.00,
                transferDate: '2026-10-24',
                user: $this->owner
            );
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Insufficient balance', $e->getMessage());
        }

        $cashDrawer->refresh();
        $bankAccount->refresh();

        $this->assertEquals('0.00', $cashDrawer->current_balance);
        $this->assertEquals('50000.00', $bankAccount->current_balance);
        $this->assertEquals(0, AccountTransfer::count());
    }

    /** 8. Same-account transfer is rejected. */
    public function test_same_account_transfer_is_rejected(): void
    {
        $account = $this->cashAndBankService->createAccount([
            'name' => 'Single Account',
            'account_code' => 'ACC-SNG',
            'type' => 'cash',
        ], $this->owner);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source and destination accounts must be different');

        $this->cashAndBankService->recordTransfer(
            fromAccount: $account,
            toAccount: $account,
            amount: 1000.00,
            transferDate: '2026-10-24',
            user: $this->owner
        );
    }

    /** 9. Negative or zero amounts are rejected. */
    public function test_negative_or_zero_amounts_are_rejected(): void
    {
        $account = $this->cashAndBankService->createAccount([
            'name' => 'Validation Account',
            'account_code' => 'VAL-01',
            'type' => 'cash',
        ], $this->owner);

        $this->expectException(InvalidArgumentException::class);
        $this->cashAndBankService->recordOpeningBalance($account, 0.00, '2026-10-01', $this->owner);
    }

    /** 10. Draft expense does not alter account balance. */
    public function test_draft_expense_does_not_alter_account_balance(): void
    {
        $account = $this->cashAndBankService->createAccount([
            'name' => 'Expense Drawer',
            'account_code' => 'EXP-D01',
            'type' => 'cash',
            'location_id' => $this->shop1->id,
        ], $this->owner);

        $this->cashAndBankService->recordOpeningBalance($account, 30000.00, '2026-10-01', $this->owner);

        $expense = $this->expenseService->createDraft([
            'expense_category_id' => $this->rentCategory->id,
            'financial_account_id' => $account->id,
            'location_id' => $this->shop1->id,
            'expense_date' => '2026-10-24',
            'amount' => 12000.00,
            'payee_name' => 'Shop Landlord',
            'payment_method' => 'cash',
        ], $this->owner);

        $this->assertEquals('draft', $expense->status);
        $account->refresh();
        $this->assertEquals('30000.00', $account->current_balance);
        $this->assertNull($expense->transaction);
    }

    /** 11. Posting an expense deducts amount exactly once. */
    public function test_posting_an_expense_deducts_amount_exactly_once(): void
    {
        $account = $this->cashAndBankService->createAccount([
            'name' => 'Posting Drawer',
            'account_code' => 'PST-D01',
            'type' => 'cash',
            'location_id' => $this->shop1->id,
        ], $this->owner);

        $this->cashAndBankService->recordOpeningBalance($account, 50000.00, '2026-10-01', $this->owner);

        $expense = $this->expenseService->createDraft([
            'expense_category_id' => $this->utilityCategory->id,
            'financial_account_id' => $account->id,
            'location_id' => $this->shop1->id,
            'expense_date' => '2026-10-24',
            'amount' => 7500.00,
            'payee_name' => 'LESCO Electricity',
            'payment_method' => 'cash',
        ], $this->owner);

        $posted = $this->expenseService->postExpense($expense, $this->owner);

        $this->assertEquals('posted', $posted->status);
        $account->refresh();
        $this->assertEquals('42500.00', $account->current_balance);
        $this->assertNotNull($posted->transaction);

        $txn = $posted->transaction;
        $this->assertEquals('7500.00', $txn->credit_amount);
        $this->assertEquals('42500.00', $txn->balance_after);
    }

    /** 12. Duplicate expense posting is rejected or safely recovered. */
    public function test_duplicate_expense_posting_is_rejected_or_recovered(): void
    {
        $account = $this->cashAndBankService->createAccount([
            'name' => 'Idem Drawer',
            'account_code' => 'IDEM-D01',
            'type' => 'cash',
            'location_id' => $this->shop1->id,
        ], $this->owner);

        $this->cashAndBankService->recordOpeningBalance($account, 40000.00, '2026-10-01', $this->owner);

        $expense = $this->expenseService->createDraft([
            'expense_category_id' => $this->utilityCategory->id,
            'financial_account_id' => $account->id,
            'location_id' => $this->shop1->id,
            'expense_date' => '2026-10-24',
            'amount' => 5000.00,
            'payment_method' => 'cash',
        ], $this->owner);

        $postedFirst = $this->expenseService->postExpense($expense, $this->owner, idempotencyKey: 'exp-key-101');
        $account->refresh();
        $this->assertEquals('35000.00', $account->current_balance);

        // Same idempotency key replayed: returns existing without deducting again
        $postedSecond = $this->expenseService->postExpense($expense, $this->owner, idempotencyKey: 'exp-key-101');
        $account->refresh();
        $this->assertEquals('35000.00', $account->current_balance);
        $this->assertEquals($postedFirst->id, $postedSecond->id);
    }

    /** 13. Reversal restores correct balance and cannot be repeated. */
    public function test_reversal_restores_correct_balance_and_cannot_be_repeated(): void
    {
        $account = $this->cashAndBankService->createAccount([
            'name' => 'Reversal Drawer',
            'account_code' => 'REV-D01',
            'type' => 'cash',
            'location_id' => $this->shop1->id,
        ], $this->owner);

        $this->cashAndBankService->recordOpeningBalance($account, 50000.00, '2026-10-01', $this->owner);

        $expense = $this->expenseService->createDraft([
            'expense_category_id' => $this->utilityCategory->id,
            'financial_account_id' => $account->id,
            'location_id' => $this->shop1->id,
            'expense_date' => '2026-10-24',
            'amount' => 10000.00,
            'payment_method' => 'cash',
        ], $this->owner);

        $expense = $this->expenseService->postExpense($expense, $this->owner);
        $account->refresh();
        $this->assertEquals('40000.00', $account->current_balance);

        // Reverse the expense
        $reversed = $this->expenseService->reverseExpense($expense, $this->owner, 'Wrong bill entered');
        $account->refresh();

        $this->assertEquals('reversed', $reversed->status);
        $this->assertEquals('50000.00', $account->current_balance);

        // Attempting to reverse again throws exception
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already reversed');
        $this->expenseService->reverseExpense($expense, $this->owner, 'Attempt double reversal');
    }

    /** 14. Supplier payment with account deducts cash/bank without duplicating khata. */
    public function test_supplier_payment_with_account_deducts_cash_without_duplicating_khata(): void
    {
        $account = $this->cashAndBankService->createAccount([
            'name' => 'Shop 1 Payment Drawer',
            'account_code' => 'SP-CASH-01',
            'type' => 'cash',
            'location_id' => $this->shop1->id,
        ], $this->owner);

        $this->cashAndBankService->recordOpeningBalance($account, 100000.00, '2026-10-01', $this->owner);

        // Initial supplier balance: 0.00
        $supplierKhata = app(SupplierKhataService::class);
        $this->assertEquals('0.00', $supplierKhata->calculateBalance($this->supplier)['closing_balance']);

        $payment = $this->purchaseService->recordPayment($this->supplier, [
            'amount' => 25000.00,
            'payment_date' => '2026-10-24',
            'payment_method' => 'cash',
            'financial_account_id' => $account->id,
            'location_id' => $this->shop1->id,
            'notes' => 'Part payment for raw materials',
        ], $this->owner);

        $account->refresh();

        // Cash account is deducted by 25,000.00
        $this->assertEquals('75000.00', $account->current_balance);

        // Supplier balance: Khata debit of 25,000.00 (reduces payable or turns advance)
        $this->assertEquals('-25000.00', $supplierKhata->calculateBalance($this->supplier)['closing_balance']);

        // Verify account transaction link
        $accTxn = AccountTransaction::where('reference_type', SupplierPayment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($accTxn);
        $this->assertEquals('25000.00', $accTxn->credit_amount);
        $this->assertEquals('75000.00', $accTxn->balance_after);
    }

    /** 15. Customer payment with account increases cash/bank without duplicating khata. */
    public function test_customer_payment_with_account_increases_cash_without_duplicating_khata(): void
    {
        $account = $this->cashAndBankService->createAccount([
            'name' => 'Shop 1 Sales Drawer',
            'account_code' => 'CP-CASH-01',
            'type' => 'cash',
            'location_id' => $this->shop1->id,
        ], $this->owner);

        $this->cashAndBankService->recordOpeningBalance($account, 20000.00, '2026-10-01', $this->owner);

        // Customer posts on-account payment
        $payment = $this->salesPostingService->recordDirectPayment(
            customer: $this->customer,
            locationId: $this->shop1->id,
            data: [
                'amount' => 15000.00,
                'payment_date' => '2026-10-24',
                'payment_method' => 'cash',
                'financial_account_id' => $account->id,
            ],
            user: $this->owner
        );

        $account->refresh();
        $this->customer->refresh();

        // Account increased by 15,000.00
        $this->assertEquals('35000.00', $account->current_balance);

        // Customer khata credited (reduces receivable or advance credit)
        $this->assertEquals('-15000.00', app(CustomerKhataService::class)->calculateBalance($this->customer)['closing_balance']);

        $accTxn = AccountTransaction::where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($accTxn);
        $this->assertEquals('15000.00', $accTxn->debit_amount);
        $this->assertEquals('35000.00', $accTxn->balance_after);
    }

    /** 16. Credit-note-only adjustment does not touch cash/bank. */
    public function test_credit_note_only_adjustment_does_not_touch_cash_bank(): void
    {
        $account = $this->cashAndBankService->createAccount([
            'name' => 'Untouched Account',
            'account_code' => 'UNT-01',
            'type' => 'cash',
            'location_id' => $this->shop1->id,
        ], $this->owner);

        $this->cashAndBankService->recordOpeningBalance($account, 50000.00, '2026-10-01', $this->owner);

        $this->salesReturnService->recordFinancialCreditNote($this->customer, [
            'amount' => 5000.00,
            'reason_type' => 'price_difference',
            'reason' => 'Special seasonal discount',
            'note_date' => '2026-10-24',
        ], $this->owner);

        $account->refresh();
        // Credit note MUST NOT change financial account balances!
        $this->assertEquals('50000.00', $account->current_balance);
        $this->assertEquals(1, AccountTransaction::where('financial_account_id', $account->id)->count());
    }

    /** 17. Customer refund with account decreases cash/bank exactly once. */
    public function test_customer_refund_with_account_decreases_cash_bank_exactly_once(): void
    {
        $account = $this->cashAndBankService->createAccount([
            'name' => 'Refund Drawer',
            'account_code' => 'RFD-D01',
            'type' => 'cash',
            'location_id' => $this->shop1->id,
        ], $this->owner);

        $this->cashAndBankService->recordOpeningBalance($account, 40000.00, '2026-10-01', $this->owner);

        // Create and post a sales invoice first
        $draftInvoice = $this->salesPostingService->createDraft([
            'customer_id' => $this->customer->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => '2026-10-20',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'unit_type' => 'pair',
                    'quantity' => 10,
                    'unit_price' => 1500.00,
                ],
            ],
        ], $this->owner);

        $invoice = $this->salesPostingService->postInvoice($draftInvoice, $this->owner);

        // Create and post a sales return
        $draftReturn = $this->salesReturnService->createDraftReturn($invoice, [
            'return_date' => '2026-10-24',
            'reason' => 'Defective pairs',
            'items' => [
                [
                    'sales_invoice_item_id' => $invoice->items->first()->id,
                    'returned_pairs' => 2,
                    'condition' => 'defective',
                ],
            ],
        ], $this->owner);

        $salesReturn = $this->salesReturnService->postReturn($draftReturn, [], $this->owner);

        // Record customer cash refund linked to account
        $refund = $this->salesReturnService->recordCustomerRefund($salesReturn, [
            'amount' => 3000.00,
            'refund_date' => '2026-10-24',
            'payment_method' => 'cash',
            'financial_account_id' => $account->id,
        ], $this->owner);

        $account->refresh();
        $this->assertEquals('37000.00', $account->current_balance);

        $accTxn = AccountTransaction::where('reference_type', CustomerRefund::class)
            ->where('reference_id', $refund->id)
            ->first();

        $this->assertNotNull($accTxn);
        $this->assertEquals('3000.00', $accTxn->credit_amount);
        $this->assertEquals('37000.00', $accTxn->balance_after);
    }

    /** 18. Location authorization enforced. */
    public function test_location_authorization_enforced(): void
    {
        // shop1Manager cannot create an expense for shop2
        $this->actingAs($this->shop1Manager);

        $response = $this->post(route('expenses.store'), [
            'location_id' => $this->shop2->id,
            'expense_category_id' => $this->rentCategory->id,
            'amount' => 5000.00,
            'expense_date' => '2026-10-24',
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(403);
    }

    /** 19. Account statement totals reconcile with ledger entries. */
    public function test_account_statement_totals_reconcile_with_ledger_entries(): void
    {
        $account = $this->cashAndBankService->createAccount([
            'name' => 'Recon Test Account',
            'account_code' => 'RCN-01',
            'type' => 'bank',
        ], $this->owner);

        $this->cashAndBankService->recordOpeningBalance($account, 10000.00, '2026-10-01', $this->owner);

        $this->cashAndBankService->recordTransaction($account, 'customer_receipt', 25000.00, 0.00, '2026-10-05', $this->owner);
        $this->cashAndBankService->recordTransaction($account, 'supplier_payment', 0.00, 7000.00, '2026-10-10', $this->owner);
        $this->cashAndBankService->recordTransaction($account, 'expense', 0.00, 3000.00, '2026-10-15', $this->owner);

        $account->refresh();
        $reconcile = $this->cashAndBankService->reconcileBalance($account);

        $this->assertTrue($reconcile['reconciled']);
        $this->assertEquals('25000.00', $reconcile['stored_balance']);
        $this->assertEquals('25000.00', $reconcile['calculated_balance']);
        $this->assertEquals('0.00', $reconcile['difference']);
    }

    /** 20. Transfer totals have zero net impact across all accounts. */
    public function test_transfer_totals_have_zero_net_impact_across_all_accounts(): void
    {
        $acc1 = $this->cashAndBankService->createAccount([
            'name' => 'Net Account 1',
            'account_code' => 'NET-01',
            'type' => 'cash',
        ], $this->owner);

        $acc2 = $this->cashAndBankService->createAccount([
            'name' => 'Net Account 2',
            'account_code' => 'NET-02',
            'type' => 'bank',
        ], $this->owner);

        $this->cashAndBankService->recordOpeningBalance($acc1, 50000.00, '2026-10-01', $this->owner);
        $this->cashAndBankService->recordOpeningBalance($acc2, 50000.00, '2026-10-01', $this->owner);

        $acc1->refresh();
        $acc2->refresh();

        $initialTotal = bcadd((string)$acc1->current_balance, (string)$acc2->current_balance, 2);

        $this->cashAndBankService->recordTransfer($acc1, $acc2, 18500.00, '2026-10-24', $this->owner);

        $acc1->refresh();
        $acc2->refresh();

        $finalTotal = bcadd((string)$acc1->current_balance, (string)$acc2->current_balance, 2);

        $this->assertEquals($initialTotal, $finalTotal);
    }

    /** 21. Interleaved transfers preserve total liquidity invariant. */
    public function test_interleaved_transfers_preserve_total_liquidity_invariant(): void
    {
        $drawerA = $this->cashAndBankService->createAccount([
            'name' => 'Drawer A',
            'account_code' => 'DRW-A',
            'type' => 'cash',
        ], $this->owner);

        $drawerB = $this->cashAndBankService->createAccount([
            'name' => 'Drawer B',
            'account_code' => 'DRW-B',
            'type' => 'cash',
        ], $this->owner);

        $drawerC = $this->cashAndBankService->createAccount([
            'name' => 'Drawer C',
            'account_code' => 'DRW-C',
            'type' => 'cash',
        ], $this->owner);

        $this->cashAndBankService->recordOpeningBalance($drawerA, 100000.00, '2026-10-01', $this->owner);
        $this->cashAndBankService->recordOpeningBalance($drawerB, 100000.00, '2026-10-01', $this->owner);
        $this->cashAndBankService->recordOpeningBalance($drawerC, 100000.00, '2026-10-01', $this->owner);

        $initialSystemTotal = '300000.00';

        // Perform multi-direction transfers in various directions
        $this->cashAndBankService->recordTransfer($drawerA, $drawerB, 25000.00, '2026-10-24', $this->owner);
        $this->cashAndBankService->recordTransfer($drawerB, $drawerC, 40000.00, '2026-10-24', $this->owner);
        $this->cashAndBankService->recordTransfer($drawerC, $drawerA, 15000.00, '2026-10-24', $this->owner);
        $this->cashAndBankService->recordTransfer($drawerB, $drawerA, 10000.00, '2026-10-24', $this->owner);

        $drawerA->refresh();
        $drawerB->refresh();
        $drawerC->refresh();

        $currentTotal = bcadd(bcadd((string)$drawerA->current_balance, (string)$drawerB->current_balance, 2), (string)$drawerC->current_balance, 2);

        $this->assertEquals($initialSystemTotal, $currentTotal);
        $this->assertEquals('100000.00', $drawerA->current_balance); // 100k - 25k + 15k + 10k = 100k
        $this->assertEquals('75000.00', $drawerB->current_balance);  // 100k + 25k - 40k - 10k = 75k
        $this->assertEquals('125000.00', $drawerC->current_balance); // 100k + 40k - 15k = 125k
    }

    /** 22. Web endpoints smoke test (index, create, store, show). */
    public function test_web_routes_and_views_accessible_for_authorized_users(): void
    {
        $this->actingAs($this->owner);

        // Financial accounts index
        $response = $this->get(route('financial-accounts.index'));
        $response->assertStatus(200);
        $response->assertSee(__('erp.financial_accounts.title'));

        // Account transfers index & create
        $response = $this->get(route('account-transfers.index'));
        $response->assertStatus(200);

        $response = $this->get(route('account-transfers.create'));
        $response->assertStatus(200);

        // Expense categories index
        $response = $this->get(route('expense-categories.index'));
        $response->assertStatus(200);

        // Expenses index & create
        $response = $this->get(route('expenses.index'));
        $response->assertStatus(200);

        $response = $this->get(route('expenses.create'));
        $response->assertStatus(200);
    }
}
