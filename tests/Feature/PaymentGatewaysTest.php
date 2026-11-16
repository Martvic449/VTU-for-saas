<?php

use App\Services\Payments\Gateways\PaystackGateway;
use App\Services\Payments\Gateways\OpayGateway;
use App\Services\Payments\Gateways\MonnifyGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('initializes and verifies Paystack payments server-side in kobo', function () {
    config(['payment_gateways.paystack.secret_key' => 'sk_test_123456789']);
    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.test/x', 'reference' => 'DEP-1']], 200),
        'https://api.paystack.co/transaction/verify/DEP-1' => Http::response(['status' => true, 'data' => ['status' => 'success', 'amount' => 50000, 'currency' => 'NGN', 'reference' => 'DEP-1', 'id' => 99]], 200),
    ]);
    $gateway = new PaystackGateway();
    $init = $gateway->initiate('DEP-1', 50000, 'NGN', ['email' => 'test@example.com']);
    $verified = $gateway->verify('DEP-1');
    expect($init['authorization_url'])->toBe('https://checkout.paystack.test/x')
        ->and($verified['status'])->toBe('successful')->and($verified['amount_minor'])->toBe(50000);
    $body = '{"event":"charge.success"}';
    expect($gateway->verifyWebhookSignature($body, ['x-paystack-signature' => hash_hmac('sha512', $body, 'sk_test_123456789')]))->toBeTrue();
    config(['payment_gateways.paystack.webhook_secret' => 'whsec_test_123']);
    $timestamp = (string) time();
    $signature = hash_hmac('sha512', $timestamp.'.'.$body, 'whsec_test_123');
    expect($gateway->verifyWebhookSignature($body, ['paystack-signature' => 't='.$timestamp.',v1=whk_test:'.$signature]))->toBeTrue();
});

it('uses OPay SHA512 status request signing and SHA3-512 callback verification', function () {
    config(['payment_gateways.opay' => ['environment' => 'sandbox', 'merchant_id' => 'merchant-1', 'public_key' => 'public-key', 'private_key' => 'private-key']]);
    Http::fake(['https://testapi.opaycheckout.com/api/v1/international/cashier/status' => Http::response(['code' => '00000', 'data' => ['status' => 'SUCCESS', 'reference' => 'DEP-2', 'amount' => ['total' => 50000, 'currency' => 'NGN']]], 200)]);
    $gateway = new OpayGateway();
    $verified = $gateway->verify('DEP-2');
    expect($verified['status'])->toBe('successful')->and($verified['amount_minor'])->toBe(50000);
    $canonical = json_encode(['country' => 'NG', 'reference' => 'DEP-2'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    Http::assertSent(fn ($request) => $request->url() === 'https://testapi.opaycheckout.com/api/v1/international/cashier/status'
        && $request->hasHeader('Authorization', 'Bearer '.hash_hmac('sha512', $canonical, 'private-key')));
    $payload = ['amount' => '50000', 'currency' => 'NGN', 'reference' => 'DEP-2', 'refunded' => false, 'status' => 'SUCCESS', 'timestamp' => now('UTC')->toIso8601String(), 'token' => 'T1', 'transactionId' => 'T1'];
    $formatted = sprintf('{Amount:"%s",Currency:"%s",Reference:"%s",Refunded:%s,Status:"%s",Timestamp:"%s",Token:"%s",TransactionID:"%s"}', $payload['amount'], $payload['currency'], $payload['reference'], 'f', $payload['status'], $payload['timestamp'], $payload['token'], $payload['transactionId']);
    $callback = json_encode(['payload' => $payload, 'sha512' => hash_hmac('sha3-512', $formatted, 'private-key'), 'type' => 'transaction-status']);
    expect($gateway->verifyWebhookSignature($callback, []))->toBeTrue();
});

it('authenticates with Monnify and verifies collection callbacks using HMAC SHA512', function () {
    config(['payment_gateways.monnify' => ['environment' => 'sandbox', 'api_key' => 'MK_TEST_KEY', 'secret_key' => 'secret', 'contract_code' => '1234567890']]);
    Cache::flush();
    Http::fake([
        'https://sandbox.monnify.com/api/v1/auth/login' => Http::response(['requestSuccessful' => true, 'responseBody' => ['accessToken' => 'access-token']], 200),
        'https://sandbox.monnify.com/api/v2/merchant/transactions/query*' => Http::response(['requestSuccessful' => true, 'responseBody' => ['paymentStatus' => 'PAID', 'amountPaid' => '500.00', 'currency' => 'NGN', 'paymentReference' => 'DEP-3', 'transactionReference' => 'MNFY-1']], 200),
    ]);
    $gateway = new MonnifyGateway();
    $verified = $gateway->verify('DEP-3');
    expect($verified['status'])->toBe('successful')->and($verified['amount_minor'])->toBe(50000);
    $raw = '{"eventType":"SUCCESSFUL_TRANSACTION"}';
    expect($gateway->verifyWebhookSignature($raw, ['monnify-signature' => hash_hmac('sha512', $raw, 'secret')]))->toBeTrue();
});
