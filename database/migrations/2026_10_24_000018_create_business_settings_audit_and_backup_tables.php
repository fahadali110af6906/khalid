<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Phase 12: Business Settings, Audit Logs & Backup Management.
     */
    public function up(): void
    {
        // 1. Business & Application Settings
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100);
            $table->longText('value')->nullable();
            $table->string('group', 50)->default('general'); // general, branding, localization, numbering, reports
            $table->string('type', 20)->default('string'); // string, integer, boolean, json, text
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index(['group', 'key']);
            $table->index(['key', 'location_id']);
        });

        // 2. Authoritative Audit Trail
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50); // login, logout, create, update, delete, post, reverse, backup_create, backup_download
            $table->string('auditable_type', 120)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('description', 255);
            $table->string('description_ur', 255)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['location_id', 'created_at']);
        });

        // 3. Database Backups
        Schema::create('database_backups', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('filename', 150)->unique();
            $table->string('disk', 50)->default('local');
            $table->string('filepath', 255);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('type', 20)->default('database'); // database, full
            $table->string('driver', 20)->default('mysql'); // mysql, mariadb, sqlite
            $table->enum('status', ['pending', 'completed', 'failed'])->default('completed');
            $table->string('checksum_sha256', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_backups');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('business_settings');
    }
};
