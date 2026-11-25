<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Phase 8: Cash Management, Bank Accounts, Expenses & Financial Integrity.
     */
    public function up(): void
    {
        // 1. Financial Accounts (Cash Drawers & Bank Accounts)
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code', 50)->unique();
            $table->string('name', 100);
            $table->enum('account_type', ['cash', 'bank']);
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('iban', 50)->nullable();
            $table->string('branch_code', 50)->nullable();
            $table->string('currency', 10)->default('PKR');
            $table->decimal('opening_balance', 14, 2)->default(0.00);
            $table->decimal('current_balance', 14, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['location_id', 'is_active'], 'fa_loc_active_idx');
            $table->index(['account_type', 'is_active'], 'fa_type_active_idx');
        });

        // 2. Account Transfers (Inter-account transfers between cash/bank)
        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 50)->unique();
            $table->foreignId('source_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('destination_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->date('transfer_date');
            $table->decimal('amount', 14, 2);
            $table->string('reason', 255);
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['posted', 'reversed'])->default('posted');
            $table->string('idempotency_key', 64)->nullable()->unique('acc_transfers_idempotency_unique');
            $table->string('request_hash', 64)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['source_account_id', 'transfer_date'], 'at_src_date_idx');
            $table->index(['destination_account_id', 'transfer_date'], 'at_dst_date_idx');
        });

        // 3. Expense Categories
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('name_ur', 100)->nullable();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Expenses
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number', 50)->unique();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->date('expense_date');
            $table->decimal('amount', 14, 2);
            $table->string('payee_name', 100)->nullable();
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque', 'online', 'other'])->default('cash');
            $table->string('description', 255);
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique('expenses_idempotency_unique');
            $table->string('request_hash', 64)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['location_id', 'status', 'expense_date'], 'exp_loc_status_date_idx');
            $table->index(['expense_category_id', 'expense_date'], 'exp_cat_date_idx');
            $table->index(['financial_account_id', 'expense_date'], 'exp_acc_date_idx');
        });

        // 5. Account Transactions (Authoritative Cash & Bank Asset Ledger)
        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 50)->unique();
            $table->foreignId('financial_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->enum('transaction_type', [
                'opening_balance',
                'cash_receipt',
                'cash_payment',
                'bank_deposit',
                'bank_withdrawal',
                'transfer_in',
                'transfer_out',
                'customer_receipt',
                'supplier_payment',
                'customer_refund',
                'expense',
                'general_income',
                'reversal',
            ]);
            $table->date('transaction_date');
            $table->decimal('debit_amount', 14, 2)->default(0.00);  // Inflow (Increases Cash/Bank Asset)
            $table->decimal('credit_amount', 14, 2)->default(0.00); // Outflow (Decreases Cash/Bank Asset)
            $table->decimal('balance_after', 14, 2);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->string('description', 255);
            $table->foreignId('transfer_id')->nullable()->constrained('account_transfers')->nullOnDelete();
            $table->boolean('is_reversal')->default(false);
            $table->unsignedBigInteger('reversed_transaction_id')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique('acc_txns_idempotency_unique');
            $table->string('request_hash', 64)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['financial_account_id', 'transaction_date'], 'atx_acc_date_idx');
            $table->index(['reference_type', 'reference_id'], 'atx_ref_idx');
            $table->index(['transaction_type', 'transaction_date'], 'atx_type_date_idx');
        });

        // 6. Optional linkages to existing payment & refund tables
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('financial_account_id')->nullable()->after('location_id')->constrained('financial_accounts')->nullOnDelete();
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->foreignId('financial_account_id')->nullable()->after('location_id')->constrained('financial_accounts')->nullOnDelete();
        });

        Schema::table('customer_refunds', function (Blueprint $table) {
            $table->foreignId('financial_account_id')->nullable()->after('location_id')->constrained('financial_accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_refunds', function (Blueprint $table) {
            $table->dropForeign(['financial_account_id']);
            $table->dropColumn('financial_account_id');
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropForeign(['financial_account_id']);
            $table->dropColumn('financial_account_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['financial_account_id']);
            $table->dropColumn('financial_account_id');
        });

        Schema::dropIfExists('account_transactions');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('account_transfers');
        Schema::dropIfExists('financial_accounts');
    }
};
