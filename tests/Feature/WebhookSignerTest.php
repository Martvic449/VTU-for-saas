<?php

use App\Services\Webhooks\WebhookSigner;

it('signs webhook bodies and rejects stale timestamps', function () {
    $signer = new WebhookSigner();
    $timestamp = time();
    $body = '{"event":"transaction.successful"}';
    $signature = $signer->sign('test-secret', $timestamp, $body);
    expect($signer->verify('test-secret', $timestamp, $body, $signature))->toBeTrue();
    expect($signer->verify('test-secret', $timestamp - 600, $body, $signature))->toBeFalse();
    expect($signer->verify('wrong-secret', $timestamp, $body, $signature))->toBeFalse();
});
