<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with core foundation data.
     */
    public function run(): void
    {
        // 1. Create System Roles
        $ownerRole = Role::firstOrCreate(['name' => 'owner'], [
            'label' => 'Owner / Super Admin',
            'description' => 'Unrestricted cross-location executive access',
            'is_system' => true,
        ]);

        $shopManagerRole = Role::firstOrCreate(['name' => 'shop_manager'], [
            'label' => 'Shop Manager',
            'description' => 'Manages assigned retail/wholesale footwear shop',
            'is_system' => true,
        ]);

        $shopCashierRole = Role::firstOrCreate(['name' => 'shop_cashier'], [
            'label' => 'Shop Cashier / Sales Staff',
            'description' => 'Fast billing and cash counter operations',
            'is_system' => true,
        ]);

        $factoryManagerRole = Role::firstOrCreate(['name' => 'factory_manager'], [
            'label' => 'Factory Manager',
            'description' => 'Footwear production orders, BoM, and raw materials',
            'is_system' => true,
        ]);

        $warehouseManagerRole = Role::firstOrCreate(['name' => 'warehouse_manager'], [
            'label' => 'Central Warehouse Manager',
            'description' => 'Consolidation, stock dispatches, and in-transit verification',
            'is_system' => true,
        ]);

        // 2. Create Permissions
        $permissions = [
            ['name' => 'view_all_locations', 'label' => 'View All Locations (Consolidated)', 'group' => 'locations'],
            ['name' => 'manage_locations', 'label' => 'Create & Edit Locations', 'group' => 'locations'],
            ['name' => 'manage_users', 'label' => 'Manage Users & Assignments', 'group' => 'users'],
            ['name' => 'view_consolidated_reports', 'label' => 'View Consolidated P&L & Balance Sheet', 'group' => 'reports'],
            ['name' => 'sales.create', 'label' => 'Create Wholesale Invoices', 'group' => 'sales'],
            ['name' => 'sales.view', 'label' => 'View Sales Records', 'group' => 'sales'],
            ['name' => 'inventory.transfer', 'label' => 'Dispatch & Receive Stock Transfers', 'group' => 'inventory'],
            ['name' => 'production.manage', 'label' => 'Manage Production Orders & BoM', 'group' => 'factory'],
        ];

        foreach ($permissions as $perm) {
            $createdPerm = Permission::firstOrCreate(['name' => $perm['name']], $perm);
            $ownerRole->givePermissionTo($createdPerm);
        }

        // Standard System Units of Measure
        \App\Models\UnitOfMeasure::firstOrCreate(['code' => 'PR'], [
            'name' => 'Pairs',
            'allow_decimals' => false,
            'is_active' => true,
        ]);
        \App\Models\UnitOfMeasure::firstOrCreate(['code' => 'CTN'], [
            'name' => 'Cartons',
            'allow_decimals' => false,
            'is_active' => true,
        ]);
        \App\Models\UnitOfMeasure::firstOrCreate(['code' => 'SQFT'], [
            'name' => 'Square Feet',
            'allow_decimals' => true,
            'is_active' => true,
        ]);
        \App\Models\UnitOfMeasure::firstOrCreate(['code' => 'KG'], [
            'name' => 'Kilograms',
            'allow_decimals' => true,
            'is_active' => true,
        ]);

        // Standard Product Categories
        \App\Models\ProductCategory::firstOrCreate(['code' => 'MEN-FORMAL'], [
            'name' => 'Men Formal Shoes',
            'type' => 'finished_good',
            'is_active' => true,
        ]);
        \App\Models\ProductCategory::firstOrCreate(['code' => 'MEN-CASUAL'], [
            'name' => 'Men Casual & Loafers',
            'type' => 'finished_good',
            'is_active' => true,
        ]);
        \App\Models\ProductCategory::firstOrCreate(['code' => 'SPORTS-JOGGER'], [
            'name' => 'Sports Shoes & Joggers',
            'type' => 'finished_good',
            'is_active' => true,
        ]);
        \App\Models\ProductCategory::firstOrCreate(['code' => 'RAW-MAT'], [
            'name' => 'Factory Raw Materials',
            'type' => 'raw_material',
            'is_active' => true,
        ]);

        $shopManagerRole->givePermissionTo('sales.create');
        $shopManagerRole->givePermissionTo('sales.view');
        $shopManagerRole->givePermissionTo('inventory.transfer');

        $factoryManagerRole->givePermissionTo('production.manage');
        $factoryManagerRole->givePermissionTo('inventory.transfer');

        // 3. Create Default Locations
        $shop1 = Location::firstOrCreate(['code' => 'LOC-SH01'], [
            'name' => 'Shop 1 — Anarkali Bazaar',
            'type' => 'shop',
            'city' => 'Lahore',
            'address' => 'Main Wholesale Shoe Market, Anarkali',
            'phone' => '+92 42 37350001',
            'is_active' => true,
        ]);

        $shop2 = Location::firstOrCreate(['code' => 'LOC-SH02'], [
            'name' => 'Shop 2 — Shah Alam Market',
            'type' => 'shop',
            'city' => 'Lahore',
            'address' => 'Shah Alam Market, Lahore',
            'phone' => '+92 42 37660002',
            'is_active' => true,
        ]);

        $shop3 = Location::firstOrCreate(['code' => 'LOC-SH03'], [
            'name' => 'Shop 3 — Rail Bazaar',
            'type' => 'shop',
            'city' => 'Faisalabad',
            'address' => 'Rail Bazaar Footwear Center, Faisalabad',
            'phone' => '+92 41 2610003',
            'is_active' => true,
        ]);

        $factory1 = Location::firstOrCreate(['code' => 'LOC-FAC01'], [
            'name' => 'Factory 1 — Sheikhupura Road',
            'type' => 'factory',
            'city' => 'Sheikhupura',
            'address' => 'Industrial Area, Sheikhupura Road',
            'phone' => '+92 56 3790001',
            'is_active' => true,
        ]);

        $factory2 = Location::firstOrCreate(['code' => 'LOC-FAC02'], [
            'name' => 'Factory 2 — Gujranwala PU Unit',
            'type' => 'factory',
            'city' => 'Gujranwala',
            'address' => 'Small Industrial Estate, Gujranwala',
            'phone' => '+92 55 3850002',
            'is_active' => true,
        ]);

        $warehouse = Location::firstOrCreate(['code' => 'LOC-WH01'], [
            'name' => 'Central Distribution Warehouse',
            'type' => 'warehouse',
            'city' => 'Lahore',
            'address' => 'Badami Bagh / Ring Road, Lahore',
            'phone' => '+92 42 37720005',
            'is_active' => true,
        ]);

        $transit = Location::firstOrCreate(['code' => 'LOC-TR01'], [
            'name' => 'In-Transit Virtual Location',
            'type' => 'transit',
            'city' => null,
            'address' => 'Consignments moving between branches via Cargo Addas',
            'phone' => null,
            'is_active' => true,
        ]);

        // 4. Create Core Users with Roles and Location Isolation Access

        // A. Khalid Malik (Owner / Super Admin)
        $owner = User::firstOrCreate(['email' => 'khalid.owner@khalidtraders.pk'], [
            'name' => 'Khalid Malik (Owner)',
            'phone' => '+92 300 8400000',
            'password' => Hash::make('Owner@Khalid2026!'),
            'is_active' => true,
            'default_location_id' => $shop1->id,
        ]);
        $owner->assignRole($ownerRole);
        // Assign all locations to owner
        $owner->locations()->sync([
            $shop1->id => ['is_primary' => true, 'can_view' => true, 'can_operate' => true],
            $shop2->id => ['is_primary' => false, 'can_view' => true, 'can_operate' => true],
            $shop3->id => ['is_primary' => false, 'can_view' => true, 'can_operate' => true],
            $factory1->id => ['is_primary' => false, 'can_view' => true, 'can_operate' => true],
            $factory2->id => ['is_primary' => false, 'can_view' => true, 'can_operate' => true],
            $warehouse->id => ['is_primary' => false, 'can_view' => true, 'can_operate' => true],
        ]);

        // B. Tariq Mehmood (Shop 1 Manager ONLY)
        $tariq = User::firstOrCreate(['email' => 'tariq.anarkali@khalidtraders.pk'], [
            'name' => 'Tariq Mehmood (Shop 1 Manager)',
            'phone' => '+92 301 7350001',
            'password' => Hash::make('Tariq@Shop1Pass!'),
            'is_active' => true,
            'default_location_id' => $shop1->id,
        ]);
        $tariq->assignRole($shopManagerRole);
        $tariq->locations()->sync([
            $shop1->id => ['is_primary' => true, 'can_view' => true, 'can_operate' => true],
        ]);

        // C. Muhammad Irfan (Shop 2 Manager ONLY)
        $irfan = User::firstOrCreate(['email' => 'irfan.shahalam@khalidtraders.pk'], [
            'name' => 'Muhammad Irfan (Shop 2 Manager)',
            'phone' => '+92 302 7660002',
            'password' => Hash::make('Irfan@Shop2Pass!'),
            'is_active' => true,
            'default_location_id' => $shop2->id,
        ]);
        $irfan->assignRole($shopManagerRole);
        $irfan->locations()->sync([
            $shop2->id => ['is_primary' => true, 'can_view' => true, 'can_operate' => true],
        ]);

        // D. Aslam Gujjar (Factory 1 Manager ONLY)
        $aslam = User::firstOrCreate(['email' => 'aslam.factory1@khalidtraders.pk'], [
            'name' => 'Muhammad Aslam (Factory 1 In-Charge)',
            'phone' => '+92 303 3790001',
            'password' => Hash::make('Aslam@Fac1Pass!'),
            'is_active' => true,
            'default_location_id' => $factory1->id,
        ]);
        $aslam->assignRole($factoryManagerRole);
        $aslam->locations()->sync([
            $factory1->id => ['is_primary' => true, 'can_view' => true, 'can_operate' => true],
        ]);

        // E. Deactivated Employee (Cannot log in)
        $inactive = User::firstOrCreate(['email' => 'inactive.user@khalidtraders.pk'], [
            'name' => 'Inactive Employee',
            'phone' => '+92 304 0000000',
            'password' => Hash::make('Inactive@Pass123!'),
            'is_active' => false,
            'default_location_id' => $shop1->id,
        ]);
        $inactive->assignRole($shopCashierRole);
    }
}
