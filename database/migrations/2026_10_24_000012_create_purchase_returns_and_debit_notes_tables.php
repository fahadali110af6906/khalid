<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Phase 6B: Purchase Returns, Return Items, and Supplier Debit Notes.
     */
    public function up(): void
    {
        // 1. Purchase Returns Header
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 50)->unique();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('return_date');
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');
            $table->decimal('total_returned_cartons', 14, 4)->default(0.0000);
            $table->decimal('total_returned_pairs', 14, 4)->default(0.0000);
            $table->decimal('total_return_amount', 14, 2)->default(0.00);
            $table->string('reason', 255);
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique('purchase_returns_idempotency_unique');
            $table->string('request_hash', 64)->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['location_id', 'status', 'return_date'], 'pr_loc_status_date_idx');
            $table->index(['supplier_id', 'status'], 'pr_supp_status_idx');
            $table->index('goods_receipt_id', 'pr_grn_idx');
            $table->index('return_date', 'pr_date_idx');
        });

        // 2. Purchase Return Items
        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('goods_receipt_item_id')->constrained('goods_receipt_items')->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('returned_cartons', 14, 4)->default(0.0000);
            $table->decimal('returned_pairs', 14, 4);
            $table->decimal('unit_cost_per_pair', 14, 4);
            $table->decimal('total_cost', 14, 2);
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['purchase_return_id', 'product_variant_id'], 'pri_pr_variant_idx');
            $table->index('goods_receipt_item_id', 'pri_gri_idx');
        });

        // 3. Supplier Debit Notes
        Schema::create('supplier_debit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('debit_note_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->enum('debit_note_type', ['purchase_return', 'financial_adjustment'])->default('financial_adjustment');
            $table->foreignId('purchase_return_id')->nullable()->constrained('purchase_returns')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->date('note_date');
            $table->decimal('amount', 14, 2);
            $table->enum('reason_type', [
                'goods_return',
                'price_difference',
                'supplier_rebate',
                'damage_compensation',
                'invoice_correction',
                'other',
            ])->default('other');
            $table->string('reason', 255);
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('posted');
            $table->string('idempotency_key', 64)->nullable()->unique('supplier_debit_notes_idempotency_unique');
            $table->string('request_hash', 64)->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'note_date'], 'sdn_supp_date_idx');
            $table->index(['location_id', 'note_date'], 'sdn_loc_date_idx');
            $table->index('purchase_return_id', 'sdn_pr_idx');
            $table->index('debit_note_type', 'sdn_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_debit_notes');
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
    }
};
