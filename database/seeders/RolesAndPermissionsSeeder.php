<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User permissions
            'view users',
            'create users',
            'edit users',
            'delete users',

            // Profile permissions
            'view profile',
            'edit profile',

            // Content management permissions
            'manage content',
            'approve content',

            // System permissions
            'manage system',
            'view logs',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions

        // Super Admin
        $superAdminRole = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdminRole->givePermissionTo(Permission::all());

        // Admin
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo([
            'view users',
            'create users',
            'edit users',
            'delete users',
            'view profile',
            'edit profile',
            'manage content',
            'approve content',
            'view logs',
        ]);

        // Tourism Organization
        $tourismOrgRole = Role::create(['name' => 'tourism_organization', 'guard_name' => 'web']);
        $tourismOrgRole->givePermissionTo([
            'view profile',
            'edit profile',
            'manage content',
        ]);

        // Travel Agency
        $travelAgencyRole = Role::create(['name' => 'travel_agency', 'guard_name' => 'web']);
        $travelAgencyRole->givePermissionTo([
            'view profile',
            'edit profile',
            'manage content',
        ]);

        // Hotel
        $hotelRole = Role::create(['name' => 'hotel', 'guard_name' => 'web']);
        $hotelRole->givePermissionTo([
            'view profile',
            'edit profile',
            'manage content',
        ]);
    }
}
