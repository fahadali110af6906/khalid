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
        Schema::table('customer_ledger_entries', function (Blueprint $table) {
            $table->unique(
                ['customer_id', 'reference_type', 'reference_id'],
                'cust_ledger_cust_ref_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_ledger_entries', function (Blueprint $table) {
            $table->dropUnique('cust_ledger_cust_ref_unique');
        });
    }
};
