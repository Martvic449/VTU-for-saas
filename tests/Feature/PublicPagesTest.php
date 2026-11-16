<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public homepage and legal pages are available to guests', function () {
    $this->get('/')->assertOk()->assertSee('Power your digital services business');
    $this->get('/privacy-policy')->assertOk()->assertSee('Privacy Policy');
    $this->get('/terms-and-conditions')->assertOk()->assertSee('Terms');
});

test('public website links to both legal documents', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('/privacy-policy')
        ->assertSee('/terms-and-conditions');
});
