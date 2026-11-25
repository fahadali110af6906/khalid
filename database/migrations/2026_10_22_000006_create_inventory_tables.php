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
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('pairs_quantity', 14, 4)->default(0.0000);
            $table->decimal('allocated_quantity', 14, 4)->default(0.0000);
            $table->decimal('avg_unit_cost', 14, 4)->default(0.0000);
            $table->timestamps();

            $table->unique(['location_id', 'product_variant_id']);
            $table->index('location_id');
            $table->index('product_variant_id');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->enum('movement_type', [
                'opening_balance',
                'adjustment',
                'purchase',
                'purchase_return',
                'sale',
                'sale_return',
                'transfer_out',
                'transfer_in',
                'production_consume',
                'production_output',
                'wastage',
            ]);
            $table->decimal('quantity_delta_pairs', 14, 4);
            $table->decimal('balance_after_pairs', 14, 4);
            $table->decimal('unit_cost', 14, 4)->default(0.0000);
            $table->decimal('total_cost', 16, 4)->default(0.0000);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['location_id', 'product_variant_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number', 50)->unique();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->string('reason', 150);
            $table->enum('status', ['draft', 'approved', 'rejected'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'status']);
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('quantity_delta_pairs', 14, 4);
            $table->decimal('unit_cost', 14, 4)->default(0.0000);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_balances');
    }
};
