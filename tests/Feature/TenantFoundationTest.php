<?php

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a tenant with a unique slug and pending status', function () {
    $tenant = Tenant::create(['name' => 'Example Reseller', 'slug' => 'example-reseller', 'status' => 'pending', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN']);
    expect($tenant->fresh()->status)->toBe('pending');
    expect($tenant->fresh()->slug)->toBe('example-reseller');
});

it('does not resolve an unverified tenant domain', function () {
    $tenant = Tenant::create(['name' => 'Example', 'slug' => 'example', 'status' => 'active', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN']);
    TenantDomain::create(['tenant_id' => $tenant->id, 'hostname' => 'example.test', 'is_primary' => true, 'verified_at' => null]);
    $this->get('http://example.test/api/v1/tenant')->assertNotFound();
});
