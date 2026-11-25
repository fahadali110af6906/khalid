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
        // Inter-location Stock Transfers
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 50)->unique();
            $table->foreignId('source_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('destination_location_id')->constrained('locations')->restrictOnDelete();
            $table->string('status', 30)->default('draft'); // draft, in_transit, completed, cancelled
            $table->date('transfer_date');
            $table->unsignedInteger('total_cartons')->default(0);
            $table->decimal('total_pairs', 12, 4)->default(0.0000);
            $table->decimal('total_cost', 14, 2)->default(0.00);
            $table->text('dispatch_notes')->nullable();
            $table->text('receive_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['source_location_id', 'status']);
            $table->index(['destination_location_id', 'status']);
            $table->index('transfer_date');
        });

        // Stock Transfer Line Items
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('quantity_cartons')->default(0);
            $table->unsignedInteger('carton_pack_qty')->default(12);
            $table->decimal('quantity_pairs', 12, 4);
            $table->decimal('unit_cost', 12, 4)->default(0.0000);
            $table->decimal('total_cost', 14, 2)->default(0.00);
            $table->decimal('received_quantity_pairs', 12, 4)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['stock_transfer_id', 'product_variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
