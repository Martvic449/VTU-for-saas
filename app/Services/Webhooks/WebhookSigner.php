<?php

namespace App\Services\Webhooks;

final class WebhookSigner
{
    public function sign(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public function verify(string $secret, int $timestamp, string $body, string $signature, int $toleranceSeconds = 300): bool
    {
        if (abs(time() - $timestamp) > $toleranceSeconds) return false;
        return hash_equals($this->sign($secret, $timestamp, $body), $signature);
    }
}
