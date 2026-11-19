<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public homepage and legal pages are available to guests', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Buy for yourself.')
        ->assertSee('Build your business.')
        ->assertSee('Frequently asked questions');

    $this->get('/privacy-policy')->assertOk()->assertSee('Privacy Policy');
    $this->get('/terms-and-conditions')->assertOk()->assertSee('Terms');
});

test('public homepage contains responsive navigation, product sections and footer', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('publicMenuToggle', false)
        ->assertSee('publicNav', false)
        ->assertSee('id="features"', false)
        ->assertSee('id="how-it-works"', false)
        ->assertSee('id="faq"', false)
        ->assertSee('public-footer', false)
        ->assertSee('Create customer account')
        ->assertSee('Apply as reseller');
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

test('password visibility controls are available on customer, reseller and sign-in forms', function () {
    $this->get('/register')->assertOk()->assertSee('data-password-toggle', false);
    $this->get('/register/reseller')->assertOk()->assertSee('data-password-toggle', false);
    $this->get('/login')->assertOk()->assertSee('data-password-toggle', false);
});

test('primary calls to action retain the green brand colour and hero has mobile layout rules', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('background:var(--green)', false)
        ->assertSee('.hero-copy h1 br{display:none}', false)
        ->assertSee('.hero-actions .btn{flex:1 1 100%', false);
});
