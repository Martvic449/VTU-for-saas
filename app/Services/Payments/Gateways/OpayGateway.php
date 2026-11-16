<?php
namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class OpayGateway implements PaymentGateway
{
    private function cfg(): array { return (array) config('payment_gateways.opay'); }
    private function base(): string { return ($this->cfg()['environment'] ?? 'sandbox') === 'live' ? 'https://liveapi.opaycheckout.com' : 'https://testapi.opaycheckout.com'; }
    private function signedRequest(array $body)
    {
        $cfg = $this->cfg(); $private = (string) ($cfg['private_key'] ?? ''); $merchant = (string) ($cfg['merchant_id'] ?? '');
        if ($private === '' || $merchant === '') throw new RuntimeException('OPay private key and merchant ID are required.');
        $canonical = $this->sortRecursively($body);
        $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return Http::withHeaders(['Authorization' => 'Bearer '.hash_hmac('sha512', $json, $private), 'MerchantId' => $merchant])->acceptJson()->asJson()->connectTimeout(5)->timeout(20);
    }
    private function sortRecursively(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) if (is_array($item)) $value[$key] = $this->sortRecursively($item);
        return $value;
    }

    public function initiate(string $depositReference, int $amountMinor, string $currency, array $customer): array
    {
        $cfg = $this->cfg(); $public = (string) ($cfg['public_key'] ?? ''); $merchant = (string) ($cfg['merchant_id'] ?? '');
        if ($public === '' || $merchant === '') throw new RuntimeException('OPay public key and merchant ID are required.');
        $body = ['country' => 'NG', 'reference' => $depositReference, 'amount' => ['total' => $amountMinor, 'currency' => $currency],
            'returnUrl' => route('payments.return', ['gateway' => 'opay']), 'callbackUrl' => route('payments.webhook', ['gateway' => 'opay']),
            'cancelUrl' => route('payments.return', ['gateway' => 'opay']), 'expireAt' => 30,
            'userInfo' => ['userEmail' => $customer['email'], 'userName' => $customer['name'] ?? $customer['email'], 'userId' => (string) ($customer['id'] ?? '')],
            'product' => ['name' => 'Wallet funding', 'description' => 'Wallet funding '.$depositReference]];
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$public, 'MerchantId' => $merchant])->acceptJson()->asJson()->connectTimeout(5)->timeout(20)->post($this->base().'/api/v1/international/cashier/create', $body);
        $json = $response->json() ?? []; $data = $json['data'] ?? [];
        if (! $response->successful() || ($json['code'] ?? '') !== '00000' || empty($data['cashierUrl'])) throw new RuntimeException('OPay could not initialize this payment.');
        return ['authorization_url' => $data['cashierUrl'], 'gateway_reference' => $depositReference, 'status' => 'pending', 'gateway_transaction_reference' => $data['orderNo'] ?? null];
    }
    public function verify(string $gatewayReference): array
    {
        $body = ['reference' => $gatewayReference, 'country' => 'NG'];
        $response = $this->signedRequest($body)->post($this->base().'/api/v1/international/cashier/status', $body);
        $json = $response->json() ?? []; $data = $json['data'] ?? [];
        $status = strtoupper((string) ($data['status'] ?? ''));
        return ['status' => ! empty($data['isVoided']) ? 'under_review' : ($response->successful() && ($json['code'] ?? '') === '00000' && $status === 'SUCCESS' ? 'successful' : (in_array($status, ['FAIL', 'CLOSE', 'CANCEL'], true) ? 'failed' : 'pending')),
            'amount_minor' => (int) data_get($data, 'amount.total', -1), 'currency' => strtoupper((string) data_get($data, 'amount.currency', '')),
            'reference' => (string) ($data['reference'] ?? ''), 'gateway_transaction_id' => (string) ($data['orderNo'] ?? '')];
    }
    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $input = json_decode($rawBody, true); $payload = $input['payload'] ?? null; $signature = (string) ($input['sha512'] ?? ''); $private = (string) ($this->cfg()['private_key'] ?? '');
        if (! is_array($payload) || $signature === '' || $private === '') return false;
        $timestamp = strtotime((string) ($payload['timestamp'] ?? ''));
        if ($timestamp === false || abs(time() - $timestamp) > 900) return false;
        $formatted = sprintf('{Amount:"%s",Currency:"%s",Reference:"%s",Refunded:%s,Status:"%s",Timestamp:"%s",Token:"%s",TransactionID:"%s"}',
            $payload['amount'] ?? '', $payload['currency'] ?? '', $payload['reference'] ?? '', ! empty($payload['refunded']) ? 't' : 'f',
            $payload['status'] ?? '', $payload['timestamp'] ?? '', $payload['token'] ?? '', $payload['transactionId'] ?? '');
        return hash_equals(hash_hmac('sha3-512', $formatted, $private), $signature);
    }
}
