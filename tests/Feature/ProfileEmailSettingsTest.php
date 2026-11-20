<?php

it('provides authenticated profile email changes with fresh email verification', function () {
    $routes = file_get_contents(base_path('routes/web.php'));
    $controller = file_get_contents(app_path('Http/Controllers/ProfileController.php'));
    $view = file_get_contents(resource_path('views/account/profile.blade.php'));
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect($routes)
        ->toContain("Route::get('/account/profile', [ProfileController::class, 'edit'])")
        ->toContain("Route::patch('/account/profile', [ProfileController::class, 'update'])");

    expect($controller)
        ->toContain("'current_password' => ['required', 'current_password']")
        ->toContain("'email_verified_at' => null")
        ->toContain('$user->sendEmailVerificationNotification()')
        ->toContain("redirect()->route('verification.notice')");

    expect($view)
        ->toContain("route('profile.update')")
        ->toContain('name="email"')
        ->toContain('name="current_password"');

    expect($layout)->toContain("route('profile.edit')");
});

it('uses valid Carbon class references on customer activity and support pages', function () {
    $activity = file_get_contents(resource_path('views/tenant/customer-activity.blade.php'));
    $support = file_get_contents(resource_path('views/support/index.blade.php'));

    expect($activity)->toContain('\\Illuminate\\Support\\Carbon::parse')->not->toContain('\\\\Illuminate');
    expect($support)->toContain('\\Illuminate\\Support\\Carbon::parse')->not->toContain('\\\\Illuminate');
});
