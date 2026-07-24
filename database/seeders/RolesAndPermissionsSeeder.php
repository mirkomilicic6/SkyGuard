<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage users',
            'manage drones',    'view drones',
            'manage flights',   'view flights',   'upload flights',
            'manage maintenance','view maintenance','report maintenance',
            'manage cameras',   'view cameras',
            'view reports',
            'manage station boundary',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo(Permission::all());

        $pilot = Role::create(['name' => 'pilot']);
        $pilot->givePermissionTo([
            'view drones',
            'view flights', 'upload flights', 'manage flights',
            'view maintenance', 'report maintenance',
            'view cameras', 'manage cameras',
            'view reports',
        ]);

        $viewer = Role::create(['name' => 'viewer']);
        $viewer->givePermissionTo([
            'view drones',
            'view flights',
            'view maintenance',
            'view cameras',
            'view reports',
            'manage station boundary',
        ]);

        $adminUser = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@dronemanager.com',
            'password' => Hash::make('password'),
        ]);
        $adminUser->assignRole('admin');
    }
}
