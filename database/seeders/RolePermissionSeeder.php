<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = [
            'platform.view', 'platform.manage', 'users.view', 'users.manage', 'tenants.view', 'tenants.manage',
            'finance.view', 'finance.adjust', 'transactions.view', 'transactions.requery', 'providers.manage',
            'services.manage', 'pricing.manage', 'kyc.review', 'support.manage', 'api.manage', 'security.manage',
        ];
        foreach ($permissions as $permission) Permission::findOrCreate($permission, 'web');
        $matrix = [
            'super-admin' => $permissions,
            'operations-admin' => ['platform.view', 'users.view', 'tenants.view', 'tenants.manage', 'transactions.view', 'transactions.requery', 'providers.manage', 'services.manage', 'support.manage'],
            'finance-admin' => ['platform.view', 'finance.view', 'finance.adjust', 'transactions.view'],
            'kyc-reviewer' => ['platform.view', 'kyc.review'],
            'tenant-owner' => ['tenants.view', 'transactions.view', 'api.manage'],
            'tenant-staff' => ['tenants.view', 'transactions.view'],
            'api-developer' => ['api.manage'],
            'customer' => [],
        ];
        foreach ($matrix as $roleName => $grants) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($grants);
        }
    }
}
