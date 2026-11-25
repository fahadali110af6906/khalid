<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Phase 6A.1: Idempotency hardening for Goods Receipts and Supplier Payments.
     */
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('notes');
            $table->string('request_hash', 64)->nullable()->after('idempotency_key');
            $table->unique('idempotency_key', 'goods_receipts_idempotency_unique');
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('notes');
            $table->string('request_hash', 64)->nullable()->after('idempotency_key');
            $table->unique('idempotency_key', 'supplier_payments_idempotency_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropUnique('supplier_payments_idempotency_unique');
            $table->dropColumn(['idempotency_key', 'request_hash']);
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropUnique('goods_receipts_idempotency_unique');
            $table->dropColumn(['idempotency_key', 'request_hash']);
        });
    }
};
