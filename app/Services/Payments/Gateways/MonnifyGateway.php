<?php
namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Monnify is the online collections product from Moniepoint. */
final class MonnifyGateway implements PaymentGateway
{
    private function base(): string { return config('payment_gateways.monnify.environment') === 'live' ? 'https://api.monnify.com' : 'https://sandbox.monnify.com'; }
    private function token(): string
    {
        $key = (string) config('payment_gateways.monnify.api_key'); $secret = (string) config('payment_gateways.monnify.secret_key');
        if ($key === '' || $secret === '') throw new RuntimeException('Monnify API credentials are not configured.');
        return Cache::remember('monnify:access-token:'.substr(hash('sha256', $key), 0, 16), now()->addMinutes(50), function () use ($key, $secret) {
            $response = Http::withBasicAuth($key, $secret)->acceptJson()->post($this->base().'/api/v1/auth/login');
            $json = $response->json() ?? []; $token = data_get($json, 'responseBody.accessToken');
            if (! $response->successful() || ! data_get($json, 'requestSuccessful') || ! is_string($token)) throw new RuntimeException('Monnify authentication failed.');
            return $token;
        });
    }
    private function request() { return Http::withToken($this->token())->acceptJson()->asJson()->connectTimeout(5)->timeout(25); }
    public function initiate(string $depositReference, int $amountMinor, string $currency, array $customer): array
    {
        $response = $this->request()->post($this->base().'/api/v1/merchant/transactions/init-transaction', [
            'amount' => intdiv($amountMinor, 100).'.'.str_pad((string) ($amountMinor % 100), 2, '0', STR_PAD_LEFT), 'customerName' => $customer['name'] ?? $customer['email'],
            'customerEmail' => $customer['email'], 'paymentReference' => $depositReference,
            'paymentDescription' => 'Wallet funding '.$depositReference, 'currencyCode' => $currency,
            'contractCode' => config('payment_gateways.monnify.contract_code'),
            'redirectUrl' => route('payments.return', ['gateway' => 'monnify']),
        ]);
        $json = $response->json() ?? []; $body = $json['responseBody'] ?? [];
        if (! $response->successful() || ! data_get($json, 'requestSuccessful') || empty($body['checkoutUrl'])) throw new RuntimeException('Monnify could not initialize this payment.');
        return ['authorization_url' => $body['checkoutUrl'], 'gateway_reference' => $depositReference, 'status' => 'pending', 'gateway_transaction_reference' => $body['transactionReference'] ?? null];
    }
    public function verify(string $gatewayReference): array
    {
        $response = $this->request()->get($this->base().'/api/v2/merchant/transactions/query', ['paymentReference' => $gatewayReference]);
        $json = $response->json() ?? []; $body = $json['responseBody'] ?? [];
        $paid = $response->successful() && data_get($json, 'requestSuccessful') && ($body['paymentStatus'] ?? '') === 'PAID';
        return ['status' => $paid ? 'successful' : (in_array(($body['paymentStatus'] ?? ''), ['FAILED', 'EXPIRED'], true) ? 'failed' : (in_array(($body['paymentStatus'] ?? ''), ['REVERSED', 'PARTIALLY_PAID', 'OVERPAID'], true) ? 'under_review' : 'pending')), 'amount_minor' => $this->nairaToMinor($body['amountPaid'] ?? null),
            'currency' => strtoupper((string) ($body['currency'] ?? $body['currencyCode'] ?? 'NGN')), 'reference' => (string) ($body['paymentReference'] ?? ''),
            'gateway_transaction_id' => (string) ($body['transactionReference'] ?? '')];
    }
    private function nairaToMinor(mixed $amount): int
    {
        if (! is_numeric($amount)) return -1;
        $parts = explode('.', (string) $amount, 2);
        if (! ctype_digit(ltrim($parts[0], '+')) || (isset($parts[1]) && ! ctype_digit($parts[1]))) return -1;
        return ((int) $parts[0] * 100) + (int) str_pad(substr($parts[1] ?? '0', 0, 2), 2, '0');
    }
    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $signature = (string) ($headers['monnify-signature'] ?? $headers['Monnify-Signature'] ?? ''); $secret = (string) config('payment_gateways.monnify.secret_key');
        return $signature !== '' && $secret !== '' && hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
    }
}
