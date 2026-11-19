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

test('shared visual system uses green branding without tilted preview elements', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('--violet:#0d9b76', false)
        ->assertSee('transform:none', false)
        ->assertSee('.sidebar{background:#102b27}', false)
        ->assertDontSee('#635bff')
        ->assertDontSee('rotate(.5deg)');
});

test('getting started and safety sections are responsive and have no stray newline marker', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('.steps-grid{grid-template-columns:1fr}', false)
        ->assertSee('.safety-band{background:#f0f5f2', false)
        ->assertDontSee('\\n<section class="safety-band">');
});

test('reseller account option uses the green brand palette', function () {
    $this->get('/get-started')
        ->assertOk()
        ->assertSee('border-color:#cfe8dc', false)
        ->assertSee('background:#e7f5ee;color:#087456', false)
        ->assertDontSee('#5149ed');
});

test('description text uses readable regular-weight sizing', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('.feature-card p,.step-card p,.audience-card p,.service-tile p,.benefit-row p,.reseller-feature p,.trust-grid p,.faq-list details p{font-size:14px', false)
        ->assertSee('font-weight:400', false);
});

test('account type selection and registration forms are mobile responsive', function () {
    $this->get('/get-started')
        ->assertOk()
        ->assertSee('account-type-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))', false)
        ->assertSee('.account-type-grid{grid-template-columns:1fr', false)
        ->assertSee('border-color:#cfe8dc', false)
        ->assertSee('background:#e7f5ee;color:#087456', false);

    $this->get('/register')->assertOk()->assertSee('.form{margin:24px auto;padding:20px 17px}', false);
    $this->get('/register/reseller')->assertOk()->assertSee('Preferred subdomain')->assertSee('business-slug', false);
});

test('account chooser uses a non-overlapping single-column mobile layout and readable footer links', function () {
    $this->get('/get-started')
        ->assertOk()
        ->assertSee('account-choice-page', false)
        ->assertSee('.account-choice-page .account-type-grid{grid-template-columns:minmax(0,1fr)!important}', false)
        ->assertSee('overflow-wrap:anywhere', false);

    $this->get('/')
        ->assertOk()
        ->assertSee('.public-footer .footer-col a,.app-footer a{font-size:14px;line-height:1.65;font-weight:400}', false);
});
