<?php

use App\Http\Controllers\PlatformWebController;
use App\Http\Controllers\WebAuthController;
use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\TenantWebController;
use App\Http\Controllers\PlatformIntegrationsWebController;
use App\Http\Controllers\PlatformFinanceController;
use App\Http\Controllers\PlatformGovernanceController;
use App\Http\Controllers\TenantDeveloperPortalController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\TenantOnboardingWebController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\TenantCustomerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HelpCentreController;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

Route::get('/', StorefrontController::class)->name('home');
Route::view('/get-started', 'auth.choose-account')->name('account.type');
Route::view('/privacy-policy', 'legal.privacy')->name('legal.privacy');
Route::view('/terms-and-conditions', 'legal.terms')->name('legal.terms');
Route::get('/customer/register', [TenantCustomerController::class, 'registerForm'])->name('tenant.customer.register');
Route::post('/customer/register', [TenantCustomerController::class, 'register'])->middleware('throttle:5,1')->name('tenant.customer.register.store');
Route::get('/payments/{gateway}/return', [DepositController::class, 'returnFromGateway'])->whereIn('gateway', ['paystack','opay','monnify'])->name('payments.return');
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [WebAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [WebAuthController::class, 'register'])->middleware('throttle:5,1');
    Route::get('/register/reseller', [TenantOnboardingWebController::class, 'show'])->name('register.reseller');
    Route::post('/register/reseller', [TenantOnboardingWebController::class, 'store'])->middleware('throttle:3,1')->name('register.reseller.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'send'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});
Route::get('/mfa/challenge', [WebAuthController::class, 'showChallenge'])->name('mfa.challenge');
Route::post('/mfa/challenge', [WebAuthController::class, 'challenge'])->middleware('throttle:10,1');
Route::get('/mfa/setup', [WebAuthController::class, 'showSetup'])->name('mfa.setup');
Route::post('/mfa/setup', [WebAuthController::class, 'confirmSetup'])->middleware('throttle:10,1');


Route::get('/email/verified', fn () => view('auth.email-verified'))->name('verification.complete');

Route::middleware('auth')->group(function (): void {
    Route::get('/email/verify', fn () => auth()->user()->hasVerifiedEmail() ? redirect()->route('dashboard') : view('auth.verify-email'))->name('verification.notice');
    Route::post('/email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) return back()->with('status', 'Your email is already verified.');
        $request->user()->sendEmailVerificationNotification();
        return back()->with('status', 'A fresh verification link has been sent.');
    })->middleware('throttle:6,1')->name('verification.send');
});

// Signed verification links must work when opened by a mail app in a fresh browser session.
Route::get('/email/verify/{id}/{hash}', [\App\Http\Controllers\EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware(['auth', 'active.user', 'privileged.mfa'])->group(function (): void {
    Route::get('/account/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/account/profile', [ProfileController::class, 'update'])->middleware('throttle:5,1')->name('profile.update');
});

Route::post('/logout', [WebAuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active.user', 'privileged.mfa', 'verified'])->group(function (): void {
    Route::get('/dashboard', CustomerDashboardController::class)->name('dashboard');
    Route::get('/reseller/dashboard', [\\App\\Http\\Controllers\\ResellerDashboardController::class, '__invoke'])->name('reseller.dashboard');
    Route::get('/account/activity', [CustomerDashboardController::class, 'activity'])->name('customer.activity');
    Route::get('/customer/dashboard', [TenantCustomerController::class, 'dashboard'])->name('tenant.customer.dashboard');
    Route::post('/customer/join', [TenantCustomerController::class, 'join'])->middleware('throttle:5,1')->name('tenant.customer.join');
    Route::post('/customer/purchase', [TenantCustomerController::class, 'purchase'])->middleware('throttle:10,1')->name('tenant.customer.purchase');
    Route::post('/dashboard/purchase', [CustomerDashboardController::class, 'purchase'])->middleware('throttle:10,1')->name('dashboard.purchase');
    Route::post('/wallet/deposits', [DepositController::class, 'store'])->middleware('throttle:5,1')->name('wallet.deposits.store');
    Route::get('/platform', [PlatformWebController::class, 'dashboard'])->name('platform.dashboard');
    Route::get('/platform/tenants', [PlatformWebController::class, 'tenants'])->name('platform.tenants');
    Route::get('/platform/transactions', [PlatformWebController::class, 'transactions'])->name('platform.transactions');
    Route::get('/platform/finance', [PlatformFinanceController::class, 'index'])->name('platform.finance');
    Route::get('/platform/reports', [PlatformGovernanceController::class, 'reports'])->name('platform.reports');
    Route::get('/platform/reports/transactions.csv', [PlatformGovernanceController::class, 'exportTransactions'])->name('platform.reports.transactions');
    Route::get('/platform/kyc', [PlatformGovernanceController::class, 'kyc'])->name('platform.kyc');
    Route::get('/platform/security', [PlatformGovernanceController::class, 'security'])->name('platform.security');
    Route::get('/platform/users', [PlatformGovernanceController::class, 'users'])->name('platform.users');
    Route::patch('/platform/users/{user}/status', [PlatformGovernanceController::class, 'updateUserStatus'])->name('platform.users.status');
    Route::get('/platform/api-activity', [PlatformGovernanceController::class, 'apiActivity'])->name('platform.api-activity');
    Route::post('/platform/security/risks/{risk}', [PlatformGovernanceController::class, 'resolveRisk'])->name('platform.security.risk');
    Route::post('/platform/kyc/{record}/review', [PlatformGovernanceController::class, 'reviewKyc'])->name('platform.kyc.review');
    Route::get('/platform/support', [PlatformGovernanceController::class, 'support'])->name('platform.support');
    Route::patch('/platform/support/{ticket}', [PlatformGovernanceController::class, 'updateSupport'])->name('platform.support.update');
    Route::get('/platform/subscriptions', [PlatformGovernanceController::class, 'subscriptions'])->name('platform.subscriptions');
    Route::post('/platform/subscriptions/plans', [PlatformGovernanceController::class, 'createPlan'])->name('platform.subscriptions.plans');
    Route::post('/platform/subscriptions/assign', [PlatformGovernanceController::class, 'assignSubscription'])->name('platform.subscriptions.assign');
    Route::get('/support', [HelpCentreController::class, 'index'])->name('support.index');
    Route::get('/support/contact', [PlatformGovernanceController::class, 'mySupport'])->name('support.contact');
    Route::post('/support', [PlatformGovernanceController::class, 'createSupport'])->name('support.create');
    Route::get('/support/articles/{slug}', [HelpCentreController::class, 'show'])->name('support.article');
    Route::post('/support/articles/{article}/feedback', [HelpCentreController::class, 'feedback'])->middleware('throttle:20,1')->name('support.article.feedback');
    Route::post('/announcements/{announcement}/dismiss', [HelpCentreController::class, 'dismissAnnouncement'])->name('announcements.dismiss');
    Route::get('/platform/content', [HelpCentreController::class, 'admin'])->name('platform.content');
    Route::post('/platform/content/categories', [HelpCentreController::class, 'saveCategory'])->name('platform.content.categories');
    Route::post('/platform/content/articles', [HelpCentreController::class, 'saveArticle'])->name('platform.content.articles');
    Route::patch('/platform/content/articles/{article}', [HelpCentreController::class, 'saveArticle'])->name('platform.content.articles.update');
    Route::delete('/platform/content/articles/{article}', [HelpCentreController::class, 'deleteArticle'])->name('platform.content.articles.delete');
    Route::post('/platform/content/announcements', [HelpCentreController::class, 'saveAnnouncement'])->name('platform.content.announcements');
    Route::patch('/platform/content/announcements/{announcement}', [HelpCentreController::class, 'saveAnnouncement'])->name('platform.content.announcements.update');
    Route::delete('/platform/content/announcements/{announcement}', [HelpCentreController::class, 'deleteAnnouncement'])->name('platform.content.announcements.delete');
    Route::post('/platform/finance/reconcile', [PlatformFinanceController::class, 'reconcile'])->name('platform.finance.reconcile');
    Route::post('/platform/finance/deposits/{deposit}/reverify', [DepositController::class, 'reverify'])->name('platform.finance.deposits.reverify');
    Route::post('/platform/finance/adjustments', [PlatformFinanceController::class, 'requestAdjustment'])->name('platform.finance.adjustments');
    Route::post('/platform/finance/adjustments/{adjustment}/approve', [PlatformFinanceController::class, 'approve'])->name('platform.finance.approve');
    Route::post('/platform/finance/adjustments/{adjustment}/reject', [PlatformFinanceController::class, 'reject'])->name('platform.finance.reject');
    Route::post('/platform/transactions/{transaction}/requery', [PlatformWebController::class, 'requeryTransaction'])->name('platform.transactions.requery');
    Route::get('/platform/integrations', [PlatformIntegrationsWebController::class, 'index'])->name('platform.integrations');
    Route::post('/platform/integrations/providers', [PlatformIntegrationsWebController::class, 'createProvider'])->name('platform.integrations.providers');
    Route::post('/platform/integrations/services', [PlatformIntegrationsWebController::class, 'createService'])->name('platform.integrations.services');
    Route::post('/platform/integrations/mappings', [PlatformIntegrationsWebController::class, 'createMapping'])->name('platform.integrations.mappings');
    Route::post('/platform/integrations/pricing', [PlatformIntegrationsWebController::class, 'createPricing'])->name('platform.integrations.pricing');
    Route::patch('/platform/integrations/{type}/{id}/status', [PlatformIntegrationsWebController::class, 'updateStatus'])->name('platform.integrations.status');
    Route::post('/platform/tenants', [PlatformWebController::class, 'createTenant'])->name('platform.tenants.create');
    Route::patch('/platform/tenants/{tenant}/status', [PlatformWebController::class, 'updateTenantStatus'])->name('platform.tenants.status');
    Route::get('/tenant/dashboard', TenantWebController::class)->middleware('tenant.membership')->name('tenant.dashboard');
    Route::get('/tenant/developers', [TenantDeveloperPortalController::class, 'index'])->middleware('tenant.membership')->name('tenant.developers');
    Route::post('/tenant/developers/clients', [TenantDeveloperPortalController::class, 'createClient'])->middleware('tenant.membership')->name('tenant.developers.clients');
    Route::post('/tenant/developers/clients/{client}/keys', [TenantDeveloperPortalController::class, 'createKey'])->middleware('tenant.membership')->name('tenant.developers.keys');
    Route::delete('/tenant/developers/clients/{client}/keys/{key}', [TenantDeveloperPortalController::class, 'revokeKey'])->middleware('tenant.membership')->name('tenant.developers.keys.revoke');
    Route::post('/tenant/developers/webhooks', [TenantDeveloperPortalController::class, 'createWebhook'])->middleware('tenant.membership')->name('tenant.developers.webhooks');
    Route::patch('/tenant/developers/webhooks/{endpoint}/status', [TenantDeveloperPortalController::class, 'setWebhookStatus'])->middleware('tenant.membership')->name('tenant.developers.webhooks.status');
});
