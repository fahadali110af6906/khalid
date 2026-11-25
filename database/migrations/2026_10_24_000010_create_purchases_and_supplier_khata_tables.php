<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Phase 6A: Suppliers, Supplier Khata Ledger, Purchase Orders, Goods Receipts, and Supplier Payments.
     */
    public function up(): void
    {
        // 1. Suppliers Master Table
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code', 30)->unique();
            $table->string('business_name', 150);
            $table->string('contact_person', 100);
            $table->string('phone', 30);
            $table->string('alternate_phone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->foreignId('primary_location_id')->constrained('locations')->restrictOnDelete();
            $table->decimal('credit_limit', 14, 2)->default(0.00);
            $table->decimal('opening_balance', 14, 2)->default(0.00); // Opening payable
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['primary_location_id', 'is_active']);
            $table->index('phone');
            $table->index('business_name');
            $table->index('city');
        });

        // 2. Supplier Khata Ledger Table
        Schema::create('supplier_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->enum('entry_type', [
                'opening_balance',
                'goods_receipt',
                'supplier_payment',
                'purchase_return',
                'adjustment_credit',
                'adjustment_debit',
            ]);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 60)->nullable();
            $table->date('entry_date');
            $table->decimal('debit_amount', 14, 2)->default(0.00); // Payments to supplier (reduces payable)
            $table->decimal('credit_amount', 14, 2)->default(0.00); // Goods received (increases payable)
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Composite unique constraint to guarantee double-entry idempotency
            $table->unique(
                ['supplier_id', 'reference_type', 'reference_id'],
                'supp_ledger_supp_ref_unique'
            );

            $table->index(['supplier_id', 'entry_date']);
            $table->index(['supplier_id', 'location_id']);
            $table->index(['reference_type', 'reference_id']);
        });

        // 3. Purchase Orders
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete(); // Receiving warehouse/shop
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->enum('status', [
                'draft',
                'ordered',
                'partially_received',
                'received',
                'cancelled',
            ])->default('draft');
            $table->unsignedInteger('total_cartons')->default(0);
            $table->decimal('total_pairs', 14, 4)->default(0.0000);
            $table->decimal('subtotal_amount', 14, 2)->default(0.00);
            $table->decimal('discount_amount', 14, 2)->default(0.00);
            $table->decimal('additional_charges', 14, 2)->default(0.00); // Freight/transport charges
            $table->decimal('total_amount', 14, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->foreignId('ordered_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['location_id', 'status', 'order_date']);
            $table->index(['supplier_id', 'status']);
            $table->index('order_date');
        });

        // 4. Purchase Order Items
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->enum('unit_type', ['carton', 'pair'])->default('carton');
            $table->decimal('quantity_cartons', 14, 4)->default(0.0000);
            $table->unsignedInteger('carton_pack_qty')->default(12);
            $table->decimal('ordered_pairs', 14, 4);
            $table->decimal('received_pairs', 14, 4)->default(0.0000);
            $table->decimal('unit_cost', 14, 2); // Cost per carton or pair based on unit_type
            $table->decimal('unit_cost_per_pair', 14, 4);
            $table->decimal('line_subtotal', 14, 2);
            $table->decimal('line_discount', 14, 2)->default(0.00);
            $table->decimal('line_total', 14, 2);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'product_variant_id']);
            $table->index('product_variant_id');
        });

        // 5. Goods Receipts (GRN)
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 50)->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('receipt_date');
            $table->string('challan_number', 100)->nullable(); // Supplier Delivery Challan / Bilty #
            $table->decimal('total_received_pairs', 14, 4)->default(0.0000);
            $table->decimal('total_received_cost', 14, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['purchase_order_id', 'receipt_date']);
            $table->index(['supplier_id', 'receipt_date']);
            $table->index(['location_id', 'receipt_date']);
        });

        // 6. Goods Receipt Items
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('received_cartons', 14, 4)->default(0.0000);
            $table->decimal('received_pairs', 14, 4);
            $table->decimal('unit_cost_per_pair', 14, 4);
            $table->decimal('total_cost', 14, 2);
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['goods_receipt_id', 'product_variant_id']);
            $table->index('purchase_order_item_id');
        });

        // 7. Supplier Payments
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 14, 2);
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque', 'online', 'other'])->default('cash');
            $table->string('reference_number', 100)->nullable(); // Cheque / Bank Transfer ID
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'payment_date']);
            $table->index(['purchase_order_id', 'created_at']);
            $table->index(['location_id', 'payment_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('supplier_ledger_entries');
        Schema::dropIfExists('suppliers');
    }
};
