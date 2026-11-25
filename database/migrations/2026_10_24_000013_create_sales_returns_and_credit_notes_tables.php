<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Phase 7: Sales Returns, Return Items, Customer Credit Notes, and Customer Refunds.
     */
    public function up(): void
    {
        // 1. Sales Returns Header
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 50)->unique();
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('return_date');
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');
            $table->decimal('total_returned_cartons', 14, 4)->default(0.0000);
            $table->decimal('total_returned_pairs', 14, 4)->default(0.0000);
            $table->decimal('total_return_amount', 14, 2)->default(0.00);
            $table->enum('refund_status', ['credited_to_khata', 'refunded', 'partially_refunded'])->default('credited_to_khata');
            $table->decimal('refunded_amount', 14, 2)->default(0.00);
            $table->string('reason', 255);
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique('sales_returns_idempotency_unique');
            $table->string('request_hash', 64)->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['location_id', 'status', 'return_date'], 'sr_loc_status_date_idx');
            $table->index(['customer_id', 'status'], 'sr_cust_status_idx');
            $table->index('sales_invoice_id', 'sr_inv_idx');
            $table->index('return_date', 'sr_date_idx');
        });

        // 2. Sales Return Items
        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->foreignId('sales_invoice_item_id')->constrained('sales_invoice_items')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('returned_cartons', 14, 4)->default(0.0000);
            $table->decimal('returned_pairs', 14, 4);
            $table->decimal('unit_price_per_pair', 14, 2);
            $table->decimal('total_amount', 14, 2);
            $table->decimal('unit_cost', 14, 4)->default(0.0000);
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->boolean('is_restocked')->default(true);
            $table->enum('condition', ['resalable', 'defective', 'damaged'])->default('resalable');
            $table->string('reason', 255)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['sales_return_id', 'product_variant_id'], 'sri_sr_variant_idx');
            $table->index('sales_invoice_item_id', 'sri_sii_idx');
        });

        // 3. Customer Credit Notes
        Schema::create('customer_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->enum('credit_note_type', ['sales_return', 'financial_adjustment'])->default('financial_adjustment');
            $table->foreignId('sales_return_id')->nullable()->constrained('sales_returns')->nullOnDelete();
            $table->foreignId('sales_invoice_id')->nullable()->constrained('sales_invoices')->nullOnDelete();
            $table->date('note_date');
            $table->decimal('amount', 14, 2);
            $table->enum('reason_type', [
                'goods_return',
                'price_difference',
                'volume_rebate',
                'customer_allowance',
                'invoice_correction',
                'other',
            ])->default('other');
            $table->string('reason', 255);
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('posted');
            $table->string('idempotency_key', 64)->nullable()->unique('customer_credit_notes_idempotency_unique');
            $table->string('request_hash', 64)->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'note_date'], 'ccn_cust_date_idx');
            $table->index(['location_id', 'note_date'], 'ccn_loc_date_idx');
            $table->index('sales_return_id', 'ccn_sr_idx');
            $table->index('credit_note_type', 'ccn_type_idx');
        });

        // 4. Customer Refunds (Cash/Bank payouts for returns or credit balances)
        Schema::create('customer_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('sales_return_id')->nullable()->constrained('sales_returns')->nullOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('refund_date');
            $table->decimal('amount', 14, 2);
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque', 'online', 'other'])->default('cash');
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique('customer_refunds_idempotency_unique');
            $table->string('request_hash', 64)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'refund_date'], 'crf_cust_date_idx');
            $table->index('sales_return_id', 'crf_sr_idx');
            $table->index(['location_id', 'refund_date'], 'crf_loc_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_refunds');
        Schema::dropIfExists('customer_credit_notes');
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
    }
};
