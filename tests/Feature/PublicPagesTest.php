<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public homepage and legal pages are available to guests', function () {
    $this->get('/')->assertOk()->assertSee('Everything digital. One simpler way to buy, sell and grow.');
    $this->get('/privacy-policy')->assertOk()->assertSee('Privacy Policy');
    $this->get('/terms-and-conditions')->assertOk()->assertSee('Terms');
});

test('public website links to both legal documents', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('/privacy-policy')
        ->assertSee('/terms-and-conditions');
});

test('visitors can choose between customer and reseller registration', function () {
    $this->get('/get-started')
        ->assertOk()
        ->assertSee('Customer account')
        ->assertSee('Reseller account')
        ->assertSee('/register')
        ->assertSee('/register/reseller');
});

test('customer and reseller registration forms provide password visibility controls', function () {
    $this->get('/register')->assertOk()->assertSee('data-password-toggle', false);
    $this->get('/register/reseller')->assertOk()->assertSee('data-password-toggle', false);
});
