<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner'], ['label' => 'Business Owner']);
        $staffRole = Role::firstOrCreate(['name' => 'shop_cashier'], ['label' => 'Shop Cashier']);

        $this->owner = User::factory()->create(['is_active' => true]);
        $this->owner->roles()->sync([$ownerRole->id]);

        $this->staff = User::factory()->create(['is_active' => true]);
        $this->staff->roles()->sync([$staffRole->id]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.locations.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_non_owner_cannot_access_location_management(): void
    {
        $response = $this->actingAs($this->staff)->get(route('admin.locations.index'));
        $response->assertForbidden();
    }

    public function test_owner_can_view_location_listing(): void
    {
        Location::create([
            'code' => 'LOC-TEST01',
            'name' => 'Test Shop Alpha',
            'type' => 'shop',
            'city' => 'Lahore',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)->get(route('admin.locations.index'));
        $response->assertOk();
        $response->assertSee('Test Shop Alpha');
        $response->assertSee('LOC-TEST01');
    }

    public function test_owner_can_view_create_location_form(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.locations.create', ['type' => 'factory']));
        $response->assertOk();
        $response->assertSee('Factory Unit');
    }

    public function test_owner_can_create_new_shop(): void
    {
        $data = [
            'name' => 'Shop 4 — Karkhana Bazaar',
            'code' => 'LOC-SH04',
            'type' => 'shop',
            'city' => 'Faisalabad',
            'address' => 'Shop #12, Karkhana Bazaar, Faisalabad',
            'phone' => '0300-9876543',
            'is_active' => 1,
        ];

        $response = $this->actingAs($this->owner)->post(route('admin.locations.store'), $data);
        $response->assertRedirect(route('admin.locations.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('locations', [
            'code' => 'LOC-SH04',
            'name' => 'Shop 4 — Karkhana Bazaar',
            'type' => 'shop',
            'city' => 'Faisalabad',
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'location_create',
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_owner_can_create_new_factory(): void
    {
        $data = [
            'name' => 'Factory 3 — Sialkot Upper Unit',
            'code' => 'LOC-FAC03',
            'type' => 'factory',
            'city' => 'Sialkot',
            'address' => 'Small Industrial Estate, Sialkot',
            'phone' => '052-4567890',
            'is_active' => 1,
        ];

        $response = $this->actingAs($this->owner)->post(route('admin.locations.store'), $data);
        $response->assertRedirect(route('admin.locations.index'));

        $this->assertDatabaseHas('locations', [
            'code' => 'LOC-FAC03',
            'name' => 'Factory 3 — Sialkot Upper Unit',
            'type' => 'factory',
        ]);
    }

    public function test_owner_can_create_new_warehouse(): void
    {
        $data = [
            'name' => 'Warehouse 2 — Rawalpindi Logistics Hub',
            'code' => 'LOC-WH02',
            'type' => 'warehouse',
            'city' => 'Rawalpindi',
            'is_active' => 1,
        ];

        $response = $this->actingAs($this->owner)->post(route('admin.locations.store'), $data);
        $response->assertRedirect(route('admin.locations.index'));

        $this->assertDatabaseHas('locations', [
            'code' => 'LOC-WH02',
            'type' => 'warehouse',
        ]);
    }

    public function test_location_code_must_be_unique(): void
    {
        Location::create([
            'code' => 'LOC-DUP01',
            'name' => 'Original Location',
            'type' => 'shop',
            'is_active' => true,
        ]);

        $data = [
            'name' => 'Duplicate Location',
            'code' => 'LOC-DUP01',
            'type' => 'shop',
        ];

        $response = $this->actingAs($this->owner)->post(route('admin.locations.store'), $data);
        $response->assertSessionHasErrors(['code']);
    }

    public function test_owner_can_edit_and_update_location(): void
    {
        $location = Location::create([
            'code' => 'LOC-SH05',
            'name' => 'Shop 5 Initial',
            'type' => 'shop',
            'city' => 'Multan',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)->get(route('admin.locations.edit', $location));
        $response->assertOk();
        $response->assertSee('Shop 5 Initial');

        $updateData = [
            'name' => 'Shop 5 — Cantt Market Updated',
            'code' => 'LOC-SH05',
            'type' => 'shop',
            'city' => 'Multan City',
            'is_active' => 1,
        ];

        $updateResponse = $this->actingAs($this->owner)->put(route('admin.locations.update', $location), $updateData);
        $updateResponse->assertRedirect(route('admin.locations.index'));

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'name' => 'Shop 5 — Cantt Market Updated',
            'city' => 'Multan City',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'location_update',
            'auditable_id' => $location->id,
        ]);
    }

    public function test_owner_can_toggle_location_active_status(): void
    {
        $location = Location::create([
            'code' => 'LOC-TOGGLE',
            'name' => 'Toggle Branch',
            'type' => 'shop',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)->patch(route('admin.locations.toggle-active', $location));
        $response->assertRedirect(route('admin.locations.index'));

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'is_active' => 0,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'location_toggle_status',
            'auditable_id' => $location->id,
        ]);
    }
}
