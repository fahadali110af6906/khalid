<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BusinessSetting;
use App\Models\DatabaseBackup;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use App\Services\Administration\AuditService;
use App\Services\Administration\BackupService;
use App\Services\Administration\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class BusinessSettingsAuditAndBackupTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $manager;
    protected Location $shop1;
    protected BackupService $backupService;
    protected AuditService $auditService;
    protected array $createdTestFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->shop1 = Location::where('code', 'LOC-SH01')->firstOrFail();
        $this->owner = User::where('email', 'khalid.owner@khalidtraders.pk')->firstOrFail();
        $this->manager = User::where('email', 'tariq.anarkali@khalidtraders.pk')->firstOrFail();

        $this->auditService = app(AuditService::class);
        $this->backupService = app(BackupService::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTestFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }

        parent::tearDown();
    }

    /**
     * Test BusinessSetting default retrieval, caching, type casting, and location overrides.
     */
    public function test_business_settings_defaults_and_get_set_helper(): void
    {
        // 1. System default fallback
        $val = BusinessSetting::get('currency_code');
        $this->assertEquals('PKR', $val);

        // 2. Setting a new value
        BusinessSetting::set('business_name', 'Khalid Footwear Emporium');
        $this->assertEquals('Khalid Footwear Emporium', BusinessSetting::get('business_name'));

        // 3. Type casting (integer)
        BusinessSetting::set('test_retention', 15, 'operational', null, 'integer');
        $intVal = BusinessSetting::get('test_retention');
        $this->assertIsInt($intVal);
        $this->assertEquals(15, $intVal);

        // 4. Type casting (boolean)
        BusinessSetting::set('test_feature_flag', true, 'operational', null, 'boolean');
        $boolVal = BusinessSetting::get('test_feature_flag');
        $this->assertIsBool($boolVal);
        $this->assertTrue($boolVal);

        // 5. Location-specific override
        BusinessSetting::set('sales_invoice_prefix', 'ANK-', 'numbering', $this->shop1->id);
        $globalPrefix = BusinessSetting::get('sales_invoice_prefix');
        $locationPrefix = BusinessSetting::get('sales_invoice_prefix', null, $this->shop1->id);

        $this->assertEquals('INV-', $globalPrefix);
        $this->assertEquals('ANK-', $locationPrefix);
    }

    /**
     * Test owner can view settings UI and update settings with audit trail logging.
     */
    public function test_owner_can_view_and_update_business_settings_with_audit_trail(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.settings.index'));
        $response->assertOk();
        $response->assertSee('Business Settings');
        $response->assertSee('Khalid Traders');

        $updateData = [
            'business_name' => 'Khalid Traders Footwear Pvt Ltd',
            'legal_name' => 'Khalid Footwear Holdings Ltd',
            'business_phone' => '+92 300 9876543',
            'business_email' => 'sales@khalidtraders.pk',
            'business_address' => 'Circular Road, Lahore, Pakistan',
            'tax_number' => 'NTN-7778889-0',
            'invoice_header_text' => 'Premier Wholesale Footwear Since 1990',
            'invoice_footer_terms' => '30 Days Net Payment Terms',
            'logo_url' => '/images/kt-brand-logo.png',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'date_format' => 'd/m/Y',
            'time_format' => 'h:i A',
            'currency_code' => 'PKR',
            'currency_symbol' => 'Rs.',
            'currency_position' => 'before',
            'decimal_places' => 2,
            'sales_invoice_prefix' => 'KT-INV-',
            'purchase_order_prefix' => 'KT-PO-',
            'goods_receipt_prefix' => 'KT-GRN-',
            'sales_return_prefix' => 'KT-SR-',
            'purchase_return_prefix' => 'KT-PR-',
            'payment_voucher_prefix' => 'KT-PMT-',
            'journal_entry_prefix' => 'KT-JV-',
            'production_order_prefix' => 'KT-MFG-',
            'default_report_range' => 'this_month',
            'backup_retention_count' => 12,
        ];

        $postResponse = $this->actingAs($this->owner)->post(route('admin.settings.update'), $updateData);
        $postResponse->assertRedirect(route('admin.settings.index'));
        $postResponse->assertSessionHas('success');

        $this->assertEquals('Khalid Traders Footwear Pvt Ltd', BusinessSetting::get('business_name'));
        $this->assertEquals('KT-INV-', BusinessSetting::get('sales_invoice_prefix'));
        $this->assertEquals(12, (int) BusinessSetting::get('backup_retention_count'));

        // Verify Audit Log entry
        $auditLog = AuditLog::where('action', 'settings_update')->latest()->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals($this->owner->id, $auditLog->user_id);
        $this->assertArrayHasKey('business_name', $auditLog->new_values);
    }

    /**
     * Test non-owner users cannot view or edit business settings.
     */
    public function test_non_owner_cannot_access_or_update_settings(): void
    {
        $getResponse = $this->actingAs($this->manager)->get(route('admin.settings.index'));
        $getResponse->assertForbidden();

        $postResponse = $this->actingAs($this->manager)->post(route('admin.settings.update'), [
            'business_name' => 'Hacked Name',
        ]);
        $postResponse->assertForbidden();
    }

    /**
     * Test AuditService scrubs sensitive passwords/tokens and AuditLog strictly enforces immutability.
     */
    public function test_audit_service_scrubs_sensitive_credentials_and_enforces_immutability(): void
    {
        // 1. Credential scrubbing
        $log = $this->auditService->log(
            action: 'credential_test',
            model: null,
            description: 'Testing credential scrubbing in audit service',
            oldValues: ['username' => 'khalid', 'password' => 'super_secret_123', 'api_token' => 'tok_abc456'],
            newValues: ['username' => 'khalid', 'password' => 'new_super_secret_999', 'api_token' => 'tok_xyz789'],
            actor: $this->owner
        );

        $this->assertEquals('***REDACTED***', $log->old_values['password']);
        $this->assertEquals('***REDACTED***', $log->old_values['api_token']);
        $this->assertEquals('***REDACTED***', $log->new_values['password']);
        $this->assertEquals('***REDACTED***', $log->new_values['api_token']);

        // 2. Strict immutability: Cannot update audit log
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit trail integrity violation');
        $log->update(['description' => 'Tampered description']);
    }

    /**
     * Test AuditLog deletion is forbidden.
     */
    public function test_audit_log_deletion_is_forbidden(): void
    {
        $log = $this->auditService->log(
            action: 'delete_test',
            model: null,
            description: 'Testing deletion guard',
            actor: $this->owner
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit trail integrity violation');
        $log->delete();
    }

    /**
     * Test owner can view audit logs and detailed diff view.
     */
    public function test_owner_can_view_audit_trail_and_filter_records(): void
    {
        $this->auditService->log(
            action: 'test_action_view',
            model: $this->shop1,
            description: 'Sample audit trail event for verification',
            oldValues: ['name' => 'Old Name'],
            newValues: ['name' => 'New Name'],
            actor: $this->owner
        );

        $response = $this->actingAs($this->owner)->get(route('admin.audit-logs.index', ['action' => 'test_action_view']));
        $response->assertOk();
        $response->assertSee('Sample audit trail event');

        $latestLog = AuditLog::where('action', 'test_action_view')->latest()->first();
        $detailResponse = $this->actingAs($this->owner)->get(route('admin.audit-logs.show', $latestLog));
        $detailResponse->assertOk();
        $detailResponse->assertSee('Old Name');
        $detailResponse->assertSee('New Name');
    }

    /**
     * Test database backup creation computes size, SHA-256 hash, and logs audit.
     */
    public function test_backup_service_creates_backup_computes_sha256_and_logs_audit(): void
    {
        $backup = $this->backupService->createBackup($this->owner);
        $this->createdTestFiles[] = $backup->filepath;

        $this->assertInstanceOf(DatabaseBackup::class, $backup);
        $this->assertEquals('completed', $backup->status);
        $this->assertFileExists($backup->filepath);
        $this->assertGreaterThan(0, $backup->size_bytes);
        $this->assertNotEmpty($backup->checksum_sha256);

        // Verify SHA256 integrity helper
        $verification = $this->backupService->verifyBackupIntegrity($backup);
        $this->assertTrue($verification['is_valid']);
        $this->assertEquals($backup->checksum_sha256, $verification['checksum']);

        // Verify audit log
        $log = AuditLog::where('action', 'backup_create')->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals($this->owner->id, $log->user_id);
    }

    /**
     * Test authorized backup download and access control.
     */
    public function test_backup_download_is_protected_and_logs_audit(): void
    {
        $backup = $this->backupService->createBackup($this->owner);
        $this->createdTestFiles[] = $backup->filepath;

        // Non-owner cannot download
        $managerResp = $this->actingAs($this->manager)->get(route('admin.backups.download', $backup));
        $managerResp->assertForbidden();

        // Owner can download
        $ownerResp = $this->actingAs($this->owner)->get(route('admin.backups.download', $backup));
        $ownerResp->assertOk();
        $this->assertStringContainsString('attachment;', $ownerResp->headers->get('content-disposition'));

        // Audit log recorded
        $downloadLog = AuditLog::where('action', 'backup_download')->latest()->first();
        $this->assertNotNull($downloadLog);
        $this->assertEquals($this->owner->id, $downloadLog->user_id);
    }

    /**
     * Test backup retention pruning removes excess backups.
     */
    public function test_backup_retention_pruning(): void
    {
        $dir = $this->backupService->getBackupDirectory();

        // Create 4 dummy completed backups
        $backups = [];
        for ($i = 1; $i <= 4; $i++) {
            $path = $dir . DIRECTORY_SEPARATOR . "dummy_backup_{$i}.sql";
            File::put($path, "-- Dummy SQL backup {$i}");
            $this->createdTestFiles[] = $path;

            $backups[] = DatabaseBackup::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'filename' => "dummy_backup_{$i}.sql",
                'filepath' => $path,
                'disk' => 'local',
                'size_bytes' => strlen("-- Dummy SQL backup {$i}"),
                'checksum_sha256' => hash_file('sha256', $path),
                'type' => 'database',
                'driver' => 'sqlite',
                'status' => 'completed',
                'created_by' => $this->owner->id,
                'created_at' => now()->subMinutes(10 - $i),
            ]);
        }

        $this->assertEquals(4, DatabaseBackup::where('status', 'completed')->count());

        // Prune keeping 2
        $pruned = $this->backupService->pruneOldBackups(2);
        $this->assertEquals(2, $pruned);
        $this->assertEquals(2, DatabaseBackup::where('status', 'completed')->count());
    }

    /**
     * Test user administration: create, update, and active toggle.
     */
    public function test_user_management_create_update_and_active_toggle(): void
    {
        $cashierRole = Role::where('name', 'shop_cashier')->firstOrFail();

        // 1. Create new user
        $storeData = [
            'name' => 'Kamran Salesman',
            'email' => 'kamran.sales@khalidtraders.pk',
            'phone' => '+92 311 1122334',
            'password' => 'secret_pass_123',
            'role_id' => $cashierRole->id,
            'default_location_id' => $this->shop1->id,
            'locations' => [$this->shop1->id],
            'is_active' => true,
        ];

        $resp = $this->actingAs($this->owner)->post(route('admin.users.store'), $storeData);
        $resp->assertRedirect(route('admin.users.index'));
        $resp->assertSessionHas('success');

        $newUser = User::where('email', 'kamran.sales@khalidtraders.pk')->firstOrFail();
        $this->assertTrue(Hash::check('secret_pass_123', $newUser->password));
        $this->assertTrue($newUser->hasRole('shop_cashier'));
        $this->assertTrue($newUser->is_active);

        $createLog = AuditLog::where('action', 'user_create')->latest()->first();
        $this->assertNotNull($createLog);

        // 2. Update user
        $updateData = [
            'name' => 'Kamran Senior Salesman',
            'email' => 'kamran.senior@khalidtraders.pk',
            'phone' => '+92 311 9988776',
            'role_id' => $cashierRole->id,
            'default_location_id' => $this->shop1->id,
            'locations' => [$this->shop1->id],
            'is_active' => true,
        ];

        $updateResp = $this->actingAs($this->owner)->put(route('admin.users.update', $newUser), $updateData);
        $updateResp->assertRedirect(route('admin.users.index'));

        $newUser->refresh();
        $this->assertEquals('Kamran Senior Salesman', $newUser->name);
        $this->assertEquals('kamran.senior@khalidtraders.pk', $newUser->email);

        // 3. Toggle active status
        $toggleResp = $this->actingAs($this->owner)->patch(route('admin.users.toggle-active', $newUser));
        $toggleResp->assertRedirect(route('admin.users.index'));

        $newUser->refresh();
        $this->assertFalse($newUser->is_active);

        $toggleLog = AuditLog::where('action', 'user_toggle_status')->latest()->first();
        $this->assertNotNull($toggleLog);
    }

    /**
     * Test safety safeguard preventing self-deactivation of owner account.
     */
    public function test_safety_protection_prevents_owner_self_deactivation(): void
    {
        $resp = $this->actingAs($this->owner)->patch(route('admin.users.toggle-active', $this->owner));
        $resp->assertRedirect(route('admin.users.index'));
        $resp->assertSessionHas('error');

        $this->owner->refresh();
        $this->assertTrue($this->owner->is_active);
    }

    /**
     * Test system health diagnostics is accessible only to owner.
     */
    public function test_system_health_diagnostics_accessible_by_owner_only(): void
    {
        $managerResp = $this->actingAs($this->manager)->get(route('admin.health.index'));
        $managerResp->assertForbidden();

        $ownerResp = $this->actingAs($this->owner)->get(route('admin.health.index'));
        $ownerResp->assertOk();
        $ownerResp->assertSee('System Health & Diagnostics');
        $ownerResp->assertSee('PHP Version');
        $ownerResp->assertSee('Database Performance');
    }
}
