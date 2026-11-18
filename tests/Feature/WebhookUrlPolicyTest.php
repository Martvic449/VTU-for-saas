<?php

use App\Services\Webhooks\WebhookUrlPolicy;

it('rejects local and private webhook destinations', function () {
    $policy = new WebhookUrlPolicy();
    expect(fn () => $policy->assertPublicHttps('http://example.com/hook'))->toThrow(\InvalidArgumentException::class);
    expect(fn () => $policy->assertPublicHttps('https://127.0.0.1/hook'))->toThrow(\InvalidArgumentException::class);
    expect(fn () => $policy->assertPublicHttps('https://localhost/hook'))->toThrow(\InvalidArgumentException::class);
});

it('pins validated webhook hostnames to a public address instead of resolving again at connect time', function () {
    $policy = new WebhookUrlPolicy();

    $options = $policy->pinnedResolveOptions('hooks.example.com', 443, ['1.1.1.1']);

    expect($options)->toHaveKey('curl.'.CURLOPT_RESOLVE)
        ->and($options['curl'][CURLOPT_RESOLVE])->toBe(['hooks.example.com:443:1.1.1.1']);
});

it('rejects a private address even when supplied as a DNS resolution result', function () {
    $policy = new WebhookUrlPolicy();

    expect(fn () => $policy->pinnedResolveOptions('hooks.example.com', 443, ['127.0.0.1']))
        ->toThrow(\InvalidArgumentException::class);
});
