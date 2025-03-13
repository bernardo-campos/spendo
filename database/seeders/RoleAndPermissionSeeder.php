<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = Role::firstOrCreate(['name' => 'Admin']);

        $userIndex = Permission::firstOrCreate(['name' => 'users.index']);
        $userEdit = Permission::firstOrCreate(['name' => 'users.edit']);

        $admin->givePermissionTo($userIndex);
        $admin->givePermissionTo($userEdit);

    }
}
