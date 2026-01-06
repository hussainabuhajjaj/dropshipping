<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AgentRoleSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'local-agent']);
        $permissions = [
            'view linehaul shipments',
            'view last mile deliveries',
            'create last mile deliveries',
            'update last mile deliveries',
        ];
        foreach ($permissions as $perm) {
            $permission = Permission::firstOrCreate(['name' => $perm]);
            $role->givePermissionTo($permission);
        }
    }
}
