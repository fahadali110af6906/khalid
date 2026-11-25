<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Phase 5B: Sales Invoices, Invoice Items, and Payments.
     */
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
            $table->unsignedInteger('total_cartons')->default(0);
            $table->decimal('total_pairs', 14, 4)->default(0.0000);
            $table->decimal('subtotal_amount', 14, 2)->default(0.00);
            $table->decimal('discount_amount', 14, 2)->default(0.00);
            $table->decimal('tax_amount', 14, 2)->default(0.00);
            $table->decimal('total_amount', 14, 2)->default(0.00);
            $table->decimal('paid_amount', 14, 2)->default(0.00);
            $table->decimal('balance_amount', 14, 2)->default(0.00);
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['location_id', 'status', 'invoice_date']);
            $table->index(['customer_id', 'status']);
            $table->index('payment_status');
            $table->index('invoice_date');
        });

        Schema::create('sales_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->enum('unit_type', ['carton', 'pair'])->default('carton');
            $table->decimal('quantity_cartons', 14, 4)->default(0.0000);
            $table->unsignedInteger('carton_pack_qty')->default(12);
            $table->decimal('quantity_pairs', 14, 4);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('unit_price_per_pair', 14, 2);
            $table->decimal('line_subtotal', 14, 2);
            $table->decimal('line_discount', 14, 2)->default(0.00);
            $table->decimal('line_total', 14, 2);
            $table->decimal('unit_cost', 14, 4)->default(0.0000);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['sales_invoice_id', 'product_variant_id']);
            $table->index('product_variant_id');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('sales_invoice_id')->nullable()->constrained('sales_invoices')->nullOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 14, 2);
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque', 'online', 'other'])->default('cash');
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'payment_date']);
            $table->index(['sales_invoice_id', 'created_at']);
            $table->index(['location_id', 'payment_date']);
            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sales_invoice_items');
        Schema::dropIfExists('sales_invoices');
    }
};
