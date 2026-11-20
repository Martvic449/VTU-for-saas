<?php

it('keeps transaction history off the customer dashboard and links it to a dedicated page', function () {
    $dashboard = file_get_contents(resource_path('views/tenant/customer-dashboard.blade.php'));
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
    $routes = file_get_contents(base_path('routes/web.php'));
    $activity = file_get_contents(resource_path('views/tenant/customer-activity.blade.php'));
    $controller = file_get_contents(app_path('Http/Controllers/CustomerDashboardController.php'));
    $support = file_get_contents(resource_path('views/support/index.blade.php'));

    expect($dashboard)
        ->toContain('href="#wallet-funding"')
        ->toContain('href="#digital-services"')
        ->not->toContain('Recent deposits')
        ->not->toContain('Recent transactions')
        ->not->toContain('Transaction history');

    expect($layout)
        ->toContain("route('customer.activity')")
        ->toContain('Transaction history');

    expect($routes)->toContain("->name('customer.activity')");
    expect($controller)->toContain("compact('wallet', 'balance', 'paymentGateways', 'services', 'purchaseKeys', 'tenantApplications')");
    expect($controller)->not->toContain("compact('wallet', 'balance', 'transactions', 'deposits'");
    expect($support)->toContain('class="support-layout"')->toContain('@media(max-width:760px)')->toContain('grid-template-columns:minmax(0,1fr)');
    expect($activity)
        ->toContain('Recent deposits')
        ->toContain('Recent transactions');
});
