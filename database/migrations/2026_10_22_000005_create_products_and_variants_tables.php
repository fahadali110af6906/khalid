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
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 30)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->enum('type', ['finished_good', 'raw_material', 'packaging'])->default('finished_good');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 30)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('code', 15)->unique();
            $table->boolean('allow_decimals')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('product_categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('base_unit_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->string('article_code', 50)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedInteger('carton_pack_qty')->default(12);
            $table->decimal('default_wholesale_rate', 14, 2)->default(0.00);
            $table->decimal('default_retail_rate', 14, 2)->default(0.00);
            $table->unsignedInteger('reorder_threshold_cartons')->default(5);
            $table->string('image_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index(['brand_id', 'is_active']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 60)->unique();
            $table->string('size', 20);
            $table->string('color', 30);
            $table->string('barcode', 60)->nullable()->unique();
            $table->decimal('wholesale_rate', 14, 2)->nullable();
            $table->decimal('retail_rate', 14, 2)->nullable();
            $table->decimal('purchase_cost', 14, 4)->default(0.0000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'size', 'color']);
            $table->index(['product_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('product_categories');
    }
};
