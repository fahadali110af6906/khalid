<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Phase 10: Factory & Production Management (BOM, Production Orders, Material Issues, Completions).
     */
    public function up(): void
    {
        // 1. Recipes / Bill of Materials (BOM)
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('recipe_number', 50)->unique();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('name', 150);
            $table->string('version', 20)->default('1.0');
            $table->decimal('yield_pairs', 14, 4)->default(1.0000);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'is_active'], 'rec_prod_active_idx');
        });

        // 2. Recipe Items (Bill of Material Components)
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('raw_material_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('quantity_per_pair', 14, 4);
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('wastage_percentage', 6, 2)->default(0.00);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['recipe_id', 'raw_material_variant_id'], 'rec_item_mat_idx');
        });

        // 3. Production Orders
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('factory_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('recipe_id')->constrained('recipes')->restrictOnDelete();
            $table->decimal('planned_quantity_pairs', 14, 4);
            $table->decimal('completed_good_pairs', 14, 4)->default(0.0000);
            $table->decimal('wastage_pairs', 14, 4)->default(0.0000);
            $table->date('start_date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['draft', 'planned', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->decimal('planned_material_cost', 14, 2)->default(0.00);
            $table->decimal('actual_material_cost', 14, 2)->default(0.00);
            $table->decimal('labor_cost', 14, 2)->default(0.00);
            $table->decimal('other_costs', 14, 2)->default(0.00);
            $table->decimal('total_production_cost', 14, 2)->default(0.00);
            $table->decimal('unit_cost_per_pair', 14, 4)->default(0.0000);
            $table->string('cancellation_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique('prod_orders_idempotency_unique');
            $table->string('request_hash', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['factory_location_id', 'status', 'start_date'], 'po_fac_status_date_idx');
            $table->index(['product_variant_id', 'status'], 'po_variant_status_idx');
            $table->index('status', 'po_status_idx');
        });

        // 4. Production Material Issues (Raw material consumption & returns)
        Schema::create('production_material_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_number', 50)->unique();
            $table->foreignId('production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('issue_date');
            $table->enum('type', ['issue', 'return'])->default('issue');
            $table->decimal('total_material_cost', 14, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['production_order_id', 'type'], 'pmi_order_type_idx');
        });

        // 5. Production Material Issue Items
        Schema::create('production_material_issue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_material_issue_id')->constrained('production_material_issues', 'id', 'pmii_issue_id_fk')->cascadeOnDelete();
            $table->foreignId('raw_material_variant_id')->constrained('product_variants', 'id', 'pmii_variant_id_fk')->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->decimal('unit_cost', 14, 4);
            $table->decimal('total_cost', 14, 2);
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['production_material_issue_id', 'raw_material_variant_id'], 'pmii_issue_variant_idx');
        });

        // 6. Production Completions (Finished goods output records)
        Schema::create('production_completions', function (Blueprint $table) {
            $table->id();
            $table->string('completion_number', 50)->unique();
            $table->foreignId('production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('completion_date');
            $table->decimal('good_pairs', 14, 4);
            $table->decimal('wastage_pairs', 14, 4)->default(0.0000);
            $table->decimal('labor_cost', 14, 2)->default(0.00);
            $table->decimal('other_costs', 14, 2)->default(0.00);
            $table->decimal('allocated_material_cost', 14, 2)->default(0.00);
            $table->decimal('total_cost', 14, 2)->default(0.00);
            $table->decimal('unit_cost_per_pair', 14, 4)->default(0.0000);
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique('prod_completions_idempotency_unique');
            $table->string('request_hash', 64)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['production_order_id', 'completion_date'], 'pmc_order_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_completions');
        Schema::dropIfExists('production_material_issue_items');
        Schema::dropIfExists('production_material_issues');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');
    }
};
