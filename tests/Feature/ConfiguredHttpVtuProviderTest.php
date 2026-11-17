<?php
use App\Models\Provider;
use App\Services\Providers\ConfiguredHttpVtuProvider;
use Illuminate\Support\Facades\Http;

it('normalizes only configured terminal statuses and leaves unknown outcomes pending', function () {
    $provider = new Provider([
        'adapter' => 'configured_http',
        'settings' => ['purchase_url' => 'https://supplier.example/purchase', 'response_status_path' => 'data.state',
            'response_reference_path' => 'data.ref', 'response_message_path' => 'message', 'response_cost_path' => 'data.cost_minor',
            'success_statuses' => ['OK'], 'failed_statuses' => ['NO']],
        'credentials_encrypted' => ['api_token' => 'sandbox-token-value'],
    ]);
    Http::fakeSequence('https://supplier.example/purchase')
        ->push(['data' => ['state' => 'OK', 'ref' => 'P-123', 'cost_minor' => 980], 'message' => 'done'], 200)
        ->push(['data' => ['state' => 'QUEUED', 'ref' => 'P-124']], 200);
    $result = (new ConfiguredHttpVtuProvider($provider))->purchase('airtime_100', ['product_code' => 'A100', 'phone' => '08000000000'], 'VTU-CLIENT-1');
    expect($result['outcome'])->toBe('successful')
        ->and($result['provider_reference'])->toBe('P-123')
        ->and($result['data']['actual_cost_minor'])->toBe(980);

    $pending = (new ConfiguredHttpVtuProvider($provider))->purchase('airtime_100', ['product_code' => 'A100'], 'VTU-CLIENT-2');
    expect($pending['outcome'])->toBe('pending');
});

it('never treats an HTTP server error as a definitive supplier success or failure', function () {
    $provider = new Provider([
        'adapter' => 'configured_http', 'settings' => ['purchase_url' => 'https://supplier.example/purchase'],
        'credentials_encrypted' => ['api_token' => 'sandbox-token-value'],
    ]);
    Http::fake(['https://supplier.example/purchase' => Http::response(['status' => 'successful', 'reference' => 'P-500'], 500)]);
    $result = (new ConfiguredHttpVtuProvider($provider))->purchase('data_plan', [], 'VTU-CLIENT-3');
    expect($result['outcome'])->toBe('pending');
});
