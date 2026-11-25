<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code', 30)->unique();
            $table->string('business_name', 150);
            $table->string('contact_person', 100);
            $table->string('phone', 30);
            $table->string('alternate_phone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->decimal('credit_limit', 14, 2)->default(0.00);
            $table->decimal('opening_balance', 14, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->foreignId('primary_location_id')->constrained('locations')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['primary_location_id', 'is_active']);
            $table->index('phone');
            $table->index('business_name');
            $table->index('city');
        });

        Schema::create('customer_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->enum('entry_type', [
                'opening_balance',
                'sale_invoice',
                'payment_received',
                'sale_return',
                'adjustment_debit',
                'adjustment_credit',
            ]);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 60)->nullable();
            $table->date('entry_date');
            $table->decimal('debit_amount', 14, 2)->default(0.00);
            $table->decimal('credit_amount', 14, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'entry_date']);
            $table->index(['customer_id', 'location_id']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('order_date');
            $table->enum('status', ['draft', 'confirmed', 'dispatched', 'cancelled'])->default('draft');
            $table->unsignedInteger('total_cartons')->default(0);
            $table->decimal('total_pairs', 14, 4)->default(0.0000);
            $table->decimal('subtotal_amount', 14, 2)->default(0.00);
            $table->decimal('discount_amount', 14, 2)->default(0.00);
            $table->decimal('net_amount', 14, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['location_id', 'status', 'order_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('customer_ledger_entries');
        Schema::dropIfExists('customers');
    }
};
