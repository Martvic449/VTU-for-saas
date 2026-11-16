<?php
namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Services\Payments\Gateways\MonnifyGateway;
use App\Services\Payments\Gateways\OpayGateway;
use App\Services\Payments\Gateways\PaystackGateway;
use InvalidArgumentException;

final class PaymentGatewayRegistry
{
    private array $gateways = ['paystack' => PaystackGateway::class, 'opay' => OpayGateway::class, 'monnify' => MonnifyGateway::class];
    public function enabled(): array
    {
        $enabled = array_values(array_intersect(array_keys($this->gateways), config('payment_gateways.enabled', [])));
        return array_values(array_filter($enabled, function (string $name): bool {
            return match ($name) {
                'paystack' => (string) config('payment_gateways.paystack.secret_key') !== '',
                'opay' => (string) config('payment_gateways.opay.merchant_id') !== '' && (string) config('payment_gateways.opay.public_key') !== '' && (string) config('payment_gateways.opay.private_key') !== '',
                'monnify' => (string) config('payment_gateways.monnify.api_key') !== '' && (string) config('payment_gateways.monnify.secret_key') !== '' && (string) config('payment_gateways.monnify.contract_code') !== '',
                default => false,
            };
        }));
    }
    public function for(string $name): PaymentGateway
    {
        if (! in_array($name, $this->enabled(), true) || ! isset($this->gateways[$name])) throw new InvalidArgumentException('The selected payment gateway is not enabled.');
        $class = $this->gateways[$name]; return app($class);
    }
}
