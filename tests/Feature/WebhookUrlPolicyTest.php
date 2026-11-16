<?php
use App\Services\Webhooks\WebhookUrlPolicy;

it('rejects local and private webhook destinations', function () {
    $policy = new WebhookUrlPolicy();
    expect(fn () => $policy->assertPublicHttps('http://example.com/hook'))->toThrow(\InvalidArgumentException::class);
    expect(fn () => $policy->assertPublicHttps('https://127.0.0.1/hook'))->toThrow(\InvalidArgumentException::class);
    expect(fn () => $policy->assertPublicHttps('https://localhost/hook'))->toThrow(\InvalidArgumentException::class);
});
