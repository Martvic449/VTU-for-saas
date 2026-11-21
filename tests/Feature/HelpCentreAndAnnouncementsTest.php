<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function helpCentreUser(string $role = 'customer'): User
{
    Role::findOrCreate($role, 'web');
    $user = User::create([
        'name' => 'Test '.$role,
        'email' => $role.'-'.uniqid().'@example.test',
        'password' => 'Correct-Horse-Battery-9',
        'status' => 'active',
        'email_verified_at' => now(),
    ]);
    $user->assignRole($role);
    return $user;
}

it('signs out the authenticated web user and clears the session', function () {
    $user = helpCentreUser();

    $this->actingAs($user)->withSession(['temporary-value' => 'should disappear'])
        ->post(route('logout'))
        ->assertRedirect(route('login'))
        ->assertSessionMissing('temporary-value')
        ->assertGuest();
});

it('shows only knowledge articles published for the signed-in audience', function () {
    $customer = helpCentreUser('customer');
    DB::table('knowledge_articles')->insert([
        ['title' => 'Customer wallet guide', 'slug' => 'customer-wallet-guide', 'content' => 'A detailed guide for customers and their wallet.', 'status' => 'published', 'audience' => 'customers', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['title' => 'Tenant API guide', 'slug' => 'tenant-api-guide', 'content' => 'A detailed guide for tenant API integrations.', 'status' => 'published', 'audience' => 'tenants', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['title' => 'Unpublished draft', 'slug' => 'unpublished-draft', 'content' => 'This draft must remain private until publication.', 'status' => 'draft', 'audience' => 'both', 'published_at' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->actingAs($customer)->get(route('support.index'))
        ->assertOk()->assertSee('Customer wallet guide')
        ->assertDontSee('Tenant API guide')->assertDontSee('Unpublished draft');
});

it('renders only announcements targeted to the signed-in audience', function () {
    $customer = helpCentreUser('customer');
    foreach ([['Customer maintenance notice', 'customers'], ['Tenant API maintenance', 'tenants']] as [$title, $audience]) {
        DB::table('platform_announcements')->insert([
            'title' => $title, 'message' => $title.' details', 'audience' => $audience,
            'priority' => 'normal', 'status' => 'published', 'is_dismissible' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $this->actingAs($customer)->get(route('support.index'))
        ->assertOk()->assertSee('Customer maintenance notice')
        ->assertDontSee('Tenant API maintenance');
});
