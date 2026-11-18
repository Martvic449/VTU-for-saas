<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\Developers\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('revokes a tenant developer API clients effective access when its owner loses tenant membership', function () {
    $tenant = Tenant::create([
        'name' => 'API Security Tenant',
        'slug' => 'api-security-tenant',
        'status' => 'active',
        'timezone' => 'Africa/Lagos',
        'currency' => 'NGN',
    ]);

    $owner = User::create([
        'name' => 'API Client Owner',
        'email' => 'api-owner@example.test',
        'password' => 'long-test-password-123',
        'status' => 'active',
    ]);

    $tenant->users()->attach($owner->id, ['role' => 'tenant-owner', 'status' => 'active']);

    $clientId = DB::table('api_clients')->insertGetId([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'name' => 'Tenant integration',
        'environment' => 'sandbox',
        'scopes' => json_encode(['transactions:read']),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $createdKey = app(ApiKeyService::class)->create($clientId, 'Initial key', ['transactions:read']);
    $headers = ['X-API-Key' => $createdKey['secret']];

    $this->withHeaders($headers)->getJson('/api/v1/developer/account')->assertOk();

    $tenant->users()->updateExistingPivot($owner->id, ['status' => 'suspended']);

    $this->withHeaders($headers)
        ->getJson('/api/v1/developer/account')
        ->assertForbidden()
        ->assertJsonPath('message', 'API client owner is no longer an active tenant member.');
});
