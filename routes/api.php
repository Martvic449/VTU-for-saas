<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlatformDashboardController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantTransactionController;
use App\Http\Controllers\DeveloperApiClientController;
use App\Http\Controllers\DeveloperApiController;
use App\Http\Controllers\TenantDomainController;
use App\Http\Controllers\TenantOnboardingController;
use App\Http\Controllers\TenantPurchaseController;
use App\Http\Controllers\PlatformOperationsController;
use App\Http\Controllers\DeveloperWebhookController;
use App\Http\Controllers\DeveloperPurchaseController;
use App\Http\Controllers\DepositController;
use Illuminate\Support\Facades\Route;

Route::post('/payments/{gateway}/webhook', [DepositController::class, 'webhook'])->whereIn('gateway', ['paystack','opay','monnify'])->name('payments.webhook');

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/auth/register-tenant', TenantOnboardingController::class)->middleware('throttle:5,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware(['auth:sanctum', 'active.user'])->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::middleware('abilities:mfa:setup')->post('/auth/mfa/setup', [AuthController::class, 'setupMfa']);
        Route::middleware('abilities:mfa:setup')->post('/auth/mfa/confirm', [AuthController::class, 'confirmMfa']);
        Route::middleware('abilities:mfa:verified')->group(function (): void {
            Route::get('/platform/dashboard', PlatformDashboardController::class);
            Route::get('/platform/tenants', [TenantController::class, 'index']);
            Route::post('/platform/tenants', [TenantController::class, 'store']);
            Route::patch('/platform/tenants/{tenant}/status', [TenantController::class, 'updateStatus']);
            Route::post('/platform/domains/{domain}/verify-platform-domain', [TenantDomainController::class, 'verifyPlatformDomain']);
            Route::patch('/platform/domains/{domain}/tls', [TenantDomainController::class, 'activateTls']);
            Route::get('/platform/providers', [PlatformOperationsController::class, 'providers']);
            Route::post('/platform/providers', [PlatformOperationsController::class, 'storeProvider']);
            Route::post('/platform/services', [PlatformOperationsController::class, 'storeService']);
            Route::post('/platform/provider-mappings', [PlatformOperationsController::class, 'storeMapping']);
            Route::post('/platform/pricing-rules', [PlatformOperationsController::class, 'storePricing']);
        });
        Route::get('/tenant', [TenantController::class, 'current']);
        Route::prefix('tenants/{tenant}')->group(function (): void {
            Route::post('/domains', [TenantDomainController::class, 'store']);
            Route::post('/domains/{domain}/verify', [TenantDomainController::class, 'verify']);
            Route::post('/api-clients', [DeveloperApiClientController::class, 'store']);
            Route::post('/api-clients/{client}/keys', [DeveloperApiClientController::class, 'createKey']);
            Route::delete('/api-clients/{client}/keys/{key}', [DeveloperApiClientController::class, 'revokeKey']);
            Route::get('/webhooks', [DeveloperWebhookController::class, 'index']);
            Route::post('/webhooks', [DeveloperWebhookController::class, 'store']);
            Route::patch('/webhooks/{endpoint}/status', [DeveloperWebhookController::class, 'setStatus']);
        });
        Route::middleware('tenant.membership')->prefix('tenant')->group(function (): void {
            Route::get('/transactions', [TenantTransactionController::class, 'index']);
            Route::post('/purchases', TenantPurchaseController::class)->middleware('throttle:20,1');
        });
    });

    Route::prefix('developer')->group(function (): void {
        Route::middleware(['developer.key'])->get('/account', function (\Illuminate\Http\Request $request) {
            return response()->json(['data' => ['client' => $request->attributes->get('api_client'), 'scopes' => $request->attributes->get('api_key')->scopes]]);
        });
        Route::middleware(['developer.key:balance:read'])->get('/balance', [DeveloperApiController::class, 'balance']);
        Route::middleware(['developer.key:transactions:write'])->post('/transactions', DeveloperPurchaseController::class);
        Route::middleware(['developer.key:transactions:read'])->get('/transactions/{reference}', [DeveloperApiController::class, 'transaction']);
    });
});
