<?php
namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PaystackGateway implements PaymentGateway
{
    private function secret(): string { return (string) config('payment_gateways.paystack.secret_key'); }
    private function request() { if ($this->secret() === '') throw new RuntimeException('Paystack secret key is not configured.'); return Http::withToken($this->secret())->acceptJson()->asJson()->connectTimeout(5)->timeout(20); }
    public function initiate(string $depositReference, int $amountMinor, string $currency, array $customer): array
    {
        $response = $this->request()->post('https://api.paystack.co/transaction/initialize', [
            'email' => $customer['email'], 'amount' => $amountMinor, 'currency' => $currency,
            'reference' => $depositReference, 'callback_url' => route('payments.return', ['gateway' => 'paystack']),
            'metadata' => ['deposit_reference' => $depositReference],
        ]);
        $json = $response->json() ?? [];
        if (! $response->successful() || ! ($json['status'] ?? false) || empty($json['data']['authorization_url'])) throw new RuntimeException('Paystack could not initialize this payment.');
        return ['authorization_url' => $json['data']['authorization_url'], 'gateway_reference' => $depositReference, 'status' => 'pending'];
    }
    public function verify(string $gatewayReference): array
    {
        $response = $this->request()->get('https://api.paystack.co/transaction/verify/'.rawurlencode($gatewayReference));
        $json = $response->json() ?? []; $data = $json['data'] ?? [];
        return ['status' => ($response->successful() && ($json['status'] ?? false) && ($data['status'] ?? '') === 'success') ? 'successful' : ((($data['status'] ?? '') === 'failed') ? 'failed' : ((($data['status'] ?? '') === 'reversed') ? 'under_review' : 'pending')),
            'amount_minor' => (int) ($data['amount'] ?? -1), 'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'reference' => (string) ($data['reference'] ?? ''), 'gateway_transaction_id' => (string) ($data['id'] ?? '')];
    }
    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $modern = (string) ($headers['paystack-signature'] ?? $headers['Paystack-Signature'] ?? '');
        if ($modern !== '') {
            $secret = (string) config('payment_gateways.paystack.webhook_secret');
            $parts = [];
            foreach (explode(',', $modern) as $part) {
                $pair = explode('=', trim($part), 2);
                if (count($pair) === 2) $parts[$pair[0]][] = $pair[1];
            }
            $timestamp = (string) ($parts['t'][0] ?? '');
            if ($secret !== '' && $timestamp !== '' && ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= 300) {
                $expected = hash_hmac('sha512', $timestamp.'.'.$rawBody, $secret);
                foreach ($parts['v1'] ?? [] as $candidate) {
                    $signature = str_contains($candidate, ':') ? substr($candidate, strpos($candidate, ':') + 1) : $candidate;
                    if (preg_match('/^[a-f0-9]{128}$/i', $signature) && hash_equals($expected, strtolower($signature))) return true;
                }
            }
        }
        $legacy = (string) ($headers['x-paystack-signature'] ?? $headers['X-Paystack-Signature'] ?? '');
        return $legacy !== '' && $this->secret() !== '' && hash_equals(hash_hmac('sha512', $rawBody, $this->secret()), $legacy);
    }
}
