<?php

use App\Http\Middleware\ResolveTenantFromHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withCommands([\App\Console\Commands\MakePlatformAdmin::class, \App\Console\Commands\RequeryPendingTransactions::class, \App\Console\Commands\RetryDueWebhookDeliveries::class, \App\Console\Commands\DispatchReservedVtuTransactions::class, \App\Console\Commands\RequeryPendingDeposits::class, \App\Console\Commands\InspectSmeplugCatalogue::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(ResolveTenantFromHost::class);
        $middleware->alias([
            'tenant.membership' => \App\Http\Middleware\RequireTenantMembership::class,
            'active.user' => \App\Http\Middleware\EnsureActiveUser::class,
            'developer.key' => \App\Http\Middleware\AuthenticateDeveloperApiKey::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'privileged.mfa' => \App\Http\Middleware\EnsurePrivilegedMfa::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Central exception reporting and redaction will be configured in the hardening phase.
    })->create();

/*
|--------------------------------------------------------------------------
| Optional private production environment
|--------------------------------------------------------------------------
|
| Hostinger's Git deployment can update the application directory. Keep
| production secrets in a sibling directory outside that Git checkout so
| deployments cannot replace the .env file. Local development and CI keep
| using the normal project-root .env file.
|
*/
$privateEnvironmentPath = dirname(__DIR__, 2).'/private-config';

if (is_file($privateEnvironmentPath.'/.env')) {
    $app->useEnvironmentPath($privateEnvironmentPath);
}

return $app;
