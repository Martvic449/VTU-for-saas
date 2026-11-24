<?php

use App\Models\Provider;
use App\Services\Providers\VtpassVtuProvider;
use App\Services\Providers\SmeplugVtuProvider;
use Illuminate\Support\Facades\Http;

it('sends VTpass API credentials, service mapping and request id using its documented POST contract', function () {
    $provider = new Provider(['environment' => 'sandbox', 'settings' => ['base_url' => 'https://sandbox.vtpass.com/api'],
        'credentials_encrypted' => ['api_key' => 'sandbox-api-key', 'secret_key' => 'sandbox-secret', 'public_key' => 'sandbox-public']]);
    Http::fake(['https://sandbox.vtpass.com/api/pay' => Http::response(['code' => '000', 'content' => ['transactions' => ['status' => 'delivered', 'transactionId' => 'VP-1', 'total_amount' => '98.50']]], 200)]);
    $result = (new VtpassVtuProvider($provider))->purchase('mtn_airtime', ['product_code' => 'mtn', 'phone' => '08011111111', 'amount' => 10000,
        '_provider_mapping' => ['service_id' => 'mtn', 'request_fields' => ['phone' => 'phone', 'amount' => 'amount']]], '202611151600TESTREFERENCE1');
    expect($result['outcome'])->toBe('successful')->and($result['provider_reference'])->toBe('VP-1')->and($result['data']['actual_cost_minor'])->toBe(9850);
    Http::assertSent(fn ($request) => $request->url() === 'https://sandbox.vtpass.com/api/pay'
        && $request->hasHeader('api-key', 'sandbox-api-key') && $request->hasHeader('secret-key', 'sandbox-secret')
        && $request['request_id'] === '202611151600TESTREFERENCE1' && $request['serviceID'] === 'mtn');
});

it('keeps VTpass ambiguous supplier states pending rather than treating response codes as final', function () {
    $provider = new Provider(['environment' => 'sandbox', 'settings' => ['base_url' => 'https://sandbox.vtpass.com/api'],
        'credentials_encrypted' => ['api_key' => 'sandbox-api-key', 'secret_key' => 'sandbox-secret']]);
    Http::fake(['https://sandbox.vtpass.com/api/pay' => Http::response(['code' => '000', 'response_description' => 'Processing'], 200)]);
    $result = (new VtpassVtuProvider($provider))->purchase('mtn_airtime', ['product_code' => 'mtn',
        '_provider_mapping' => ['service_id' => 'mtn']], '202611151601TESTREFERENCE2');
    expect($result['outcome'])->toBe('pending');
});

it('submits SMEPlug data purchases using the official bearer auth, endpoint and payload fields', function () {
    $provider = new Provider(['environment' => 'sandbox', 'credentials_encrypted' => ['api_token' => 'smeplug-private-key'],
        'settings' => ['response_status_path' => 'data.status', 'response_reference_path' => 'data.transaction_reference',
            'response_message_path' => 'message', 'success_statuses' => ['success'], 'failed_statuses' => ['failed']]]);
    Http::fake(['https://smeplug.ng/api/v1/data/purchase' => Http::response([
        'status' => true, 'message' => 'accepted', 'data' => ['status' => 'success', 'transaction_reference' => 'SME-1001'],
    ], 200)]);

    $result = (new SmeplugVtuProvider($provider))->purchase('mtn_data', [
        'phone' => '08011111111', 'product_code' => '123',
        '_provider_mapping' => ['network_id' => 1, 'plan_id' => 123, 'smeplug_endpoint' => 'data/purchase'],
    ], 'VTU-CLIENT-1001');

    expect($result['outcome'])->toBe('successful')->and($result['provider_reference'])->toBe('SME-1001');
    Http::assertSent(fn ($request) => $request->url() === 'https://smeplug.ng/api/v1/data/purchase'
        && $request->hasHeader('Authorization', 'Bearer smeplug-private-key')
        && $request['network_id'] === 1 && $request['plan_id'] === 123
        && $request['phone'] === '08011111111' && $request['customer_reference'] === 'VTU-CLIENT-1001');
});

it('uses SMEPlug transaction status endpoint for requery and keeps unknown response states pending', function () {
    $provider = new Provider(['credentials_encrypted' => ['api_token' => 'smeplug-private-key'],
        'settings' => ['response_status_path' => 'data.status', 'response_reference_path' => 'data.transaction_reference',
            'success_statuses' => ['success'], 'failed_statuses' => ['failed']]]);
    Http::fake(['https://smeplug.ng/api/v1/transactions/SME-2001' => Http::response([
        'status' => true, 'data' => ['status' => 'processing', 'transaction_reference' => 'SME-2001'],
    ], 200)]);

    $result = (new SmeplugVtuProvider($provider))->requery('SME-2001', 'CLIENT-2001');
    expect($result['outcome'])->toBe('pending')->and($result['provider_reference'])->toBe('SME-2001');
    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && $request->url() === 'https://smeplug.ng/api/v1/transactions/SME-2001'
        && $request->hasHeader('Authorization', 'Bearer smeplug-private-key'));
});

it('keeps SMEPlug response states pending when no response mapping has been verified', function () {
    $provider = new Provider(['credentials_encrypted' => ['api_token' => 'smeplug-private-key'], 'settings' => []]);
    Http::fake(['https://smeplug.ng/api/v1/airtime/purchase' => Http::response(['status' => true, 'message' => 'accepted'], 200)]);
    $result = (new SmeplugVtuProvider($provider))->purchase('airtime', [
        'phone' => '08011111111', 'amount' => 100,
        '_provider_mapping' => ['network_id' => 1, 'smeplug_endpoint' => 'airtime/purchase'],
    ], 'CLIENT-3001');
    expect($result['outcome'])->toBe('pending');
});

it('reads SMEPlug network and data-plan catalogues through the documented read-only endpoints', function () {
    $provider = new Provider(['adapter' => 'smeplug', 'status' => 'inactive',
        'credentials_encrypted' => ['api_token' => 'smeplug-private-key']]);
    Http::fake([
        'https://smeplug.ng/api/v1/networks' => Http::response(['status' => true, 'data' => [['id' => 1, 'name' => 'MTN']]], 200),
        'https://smeplug.ng/api/v1/data/plans' => Http::response(['status' => true, 'data' => ['1' => [['id' => 123]]]], 200),
    ]);
    $client = new \App\Services\Providers\SmeplugReadOnlyClient();
    expect($client->get($provider, 'networks')['data'][0]['id'])->toBe(1)
        ->and($client->get($provider, 'data-plans')['data']['1'][0]['id'])->toBe(123);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer smeplug-private-key'));
});


it('retrieves VTpass service categories, services and variations using sandbox GET authentication', function () {
    $provider = new Provider([
        'adapter' => 'vtpass',
        'environment' => 'sandbox',
        'credentials_encrypted' => ['api_key' => 'vt-api', 'public_key' => 'vt-public', 'secret_key' => 'vt-secret'],
        'settings' => ['base_url' => 'https://sandbox.vtpass.com/api'],
    ]);
    Http::fake([
        'https://sandbox.vtpass.com/api/service-categories' => Http::response([
            'response_description' => '000', 'content' => [['identifier' => 'data', 'name' => 'Data Services']],
        ], 200),
        'https://sandbox.vtpass.com/api/services*' => Http::response([
            'response_description' => '000', 'content' => [['serviceID' => 'mtn-data', 'name' => 'MTN Data', 'product_type' => 'variation']],
        ], 200),
        'https://sandbox.vtpass.com/api/service-variations*' => Http::response([
            'response_description' => '000', 'content' => ['serviceID' => 'mtn-data', 'variations' => [
                ['variation_code' => 'mtn-100', 'name' => 'MTN 100MB', 'variation_amount' => '100.00', 'fixedPrice' => 'Yes'],
            ]],
        ], 200),
    ]);

    $client = new \App\Services\Providers\VtpassReadOnlyCatalogueClient();
    expect($client->get($provider, 'service-categories')['content'][0]['identifier'])->toBe('data')
        ->and($client->get($provider, 'services', 'data')['content'][0]['serviceID'])->toBe('mtn-data')
        ->and($client->get($provider, 'service-variations', 'mtn-data')['content']['variations'][0]['variation_code'])->toBe('mtn-100');

    Http::assertSent(fn ($request) => $request->url() === 'https://sandbox.vtpass.com/api/services?identifier=data'
        && $request->hasHeader('api-key', 'vt-api') && $request->hasHeader('public-key', 'vt-public'));
});

it('refuses VTpass catalogue discovery with live credentials or an untrusted endpoint', function () {
    $provider = new Provider(['adapter' => 'vtpass', 'environment' => 'live',
        'credentials_encrypted' => ['api_key' => 'vt-api', 'public_key' => 'vt-public'],
        'settings' => ['base_url' => 'https://vtpass.com/api']]);
    expect(fn () => (new \App\Services\Providers\VtpassReadOnlyCatalogueClient())->get($provider, 'service-categories'))
        ->toThrow(RuntimeException::class);
});


it('uses the selected VTpass catalogue variation and supplier amount when purchasing a mapped product', function () {
    $provider = new Provider(['environment' => 'sandbox', 'settings' => ['base_url' => 'https://sandbox.vtpass.com/api'],
        'credentials_encrypted' => ['api_key' => 'sandbox-api-key', 'secret_key' => 'sandbox-secret', 'public_key' => 'sandbox-public']]);
    Http::fake(['https://sandbox.vtpass.com/api/pay' => Http::response([
        'code' => '000', 'content' => ['transactions' => ['status' => 'delivered', 'transactionId' => 'VP-VARIATION-1']],
    ], 200)]);

    $result = (new VtpassVtuProvider($provider))->purchase('mtn_data', ['phone' => '08011111111', '_provider_mapping' => [
        'service_id' => 'mtn-data', 'variation_code' => 'mtn-100mb-100', 'supplier_amount' => '100.00',
    ]], '202611241455VARIATIONTEST1');

    expect($result['outcome'])->toBe('successful');
    Http::assertSent(fn ($request) => $request->url() === 'https://sandbox.vtpass.com/api/pay'
        && $request['serviceID'] === 'mtn-data'
        && $request['variation_code'] === 'mtn-100mb-100'
        && $request['amount'] === '100.00');
});
