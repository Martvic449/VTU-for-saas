<?php

namespace App\Contracts\Payments;

interface PaymentGateway
{
    /** @return array{authorization_url?:string, gateway_reference:string, status:string} */
    public function initiate(string $depositReference, int $amountMinor, string $currency, array $customer): array;

    /** Must verify payment server-to-server; never trust a browser redirect. */
    public function verify(string $gatewayReference): array;

    public function verifyWebhookSignature(string $rawBody, array $headers): bool;
}
