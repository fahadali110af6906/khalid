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
        // 1. Employees Table (Shops, Factories, Warehouses)
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 50)->unique();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 50)->nullable();
            $table->string('cnic', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 60)->nullable();
            $table->string('designation', 100); // Salesman, Karigar, Helper, Supervisor, Manager, etc.
            $table->enum('employment_type', ['monthly', 'daily', 'piece_rate'])->default('monthly');
            $table->decimal('monthly_salary', 12, 2)->default(0.00);
            $table->decimal('daily_rate', 10, 2)->nullable();
            $table->date('joining_date')->nullable();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();
            $table->enum('status', ['active', 'on_leave', 'resigned', 'terminated'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['location_id', 'status'], 'emp_loc_status_idx');
            $table->index(['name', 'phone'], 'emp_name_phone_idx');
        });

        // 2. Employee Transactions (Salaries, Advances / Kharcha, Bonuses, Deductions)
        Schema::create('employee_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 50)->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->enum('type', ['salary_payment', 'advance_kharcha', 'bonus', 'deduction'])->default('advance_kharcha');
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date');
            $table->string('month_year', 30)->nullable(); // e.g. "October 2026"
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque', 'other'])->default('cash');
            $table->string('description', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'transaction_date'], 'emp_tx_emp_date_idx');
            $table->index(['location_id', 'type', 'transaction_date'], 'emp_tx_loc_type_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_transactions');
        Schema::dropIfExists('employees');
    }
};
