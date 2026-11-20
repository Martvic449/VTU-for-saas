<?php

it('provides customer dashboard shortcuts and a combined deposits and transactions destination', function () {
    $dashboard = file_get_contents(resource_path('views/tenant/customer-dashboard.blade.php'));
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect($dashboard)
        ->toContain('href="#wallet-funding"')
        ->toContain('href="#digital-services"')
        ->toContain('href="#account-activity"')
        ->toContain('id="wallet-funding"')
        ->toContain('id="digital-services"')
        ->toContain('id="account-activity"')
        ->toContain('Recent deposits')
        ->toContain('Recent transactions');

    expect($layout)
        ->toContain('Fund wallet')
        ->toContain('Buy digital services')
        ->toContain('Transactions')
        ->toContain('deposits');
});
