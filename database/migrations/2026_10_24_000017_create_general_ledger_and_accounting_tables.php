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
        // 1. Chart of Accounts (COA)
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_group')->default(false); // True for header categories, False for postable accounts
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // Protects system-critical integration accounts
            $table->string('system_identifier', 64)->nullable()->unique(); // e.g., 'cash_in_hand', 'accounts_receivable'
            $table->enum('normal_balance', ['debit', 'credit'])->default('debit');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['parent_id', 'is_group']);
        });

        // 2. Journal Entries (General Ledger Header)
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number', 50)->unique();
            $table->date('entry_date');
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->text('description');
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('posted');
            $table->decimal('total_debit', 14, 2)->default(0.00);
            $table->decimal('total_credit', 14, 2)->default(0.00);
            $table->foreignId('reversed_by_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('reversal_of_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['entry_date', 'status']);
            $table->index(['location_id', 'entry_date']);
            $table->index(['source_type', 'source_id']);
        });

        // 3. Journal Entry Lines (General Ledger Line Items)
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->unsignedInteger('line_number')->default(1);
            $table->decimal('debit', 14, 2)->default(0.00);
            $table->decimal('credit', 14, 2)->default(0.00);
            $table->string('description', 255)->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();

            $table->index(['journal_entry_id', 'line_number']);
            $table->index(['chart_of_account_id', 'location_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
    }
};
