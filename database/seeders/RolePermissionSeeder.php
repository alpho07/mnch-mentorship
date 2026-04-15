<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles
        $roles = [
            'super_admin', // Use snake_case for consistency with Filament Shield
            'admin',
            'division',
            'national',
            'county',
            'subcounty',
            'facility_mentor',
            'spoke_mentor',
            'spoke_mentor_lead',
            'division_lead',
            'national_mentor_lead',
            'county_mentor_lead',
            'subcounty_mentor_lead',
            'facility_mentor_lead',
            'spoke_mentor_lead',
            'mentee',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web'
            ]);
        }

        // Assign all permissions to Super Admin
        $superAdmin = Role::findByName('super_admin');
        if (Permission::count() > 0) {
            $superAdmin->syncPermissions(Permission::all());
        }

        // You can define specific permissions for other roles here
        // Example:
        // $mentee = Role::findByName('mentee');
        // $mentee->givePermissionTo(['view_posts', 'create_posts']);
    }
}
