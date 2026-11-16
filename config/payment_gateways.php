<?php
return [
    'default' => env('PAYMENT_GATEWAY_DEFAULT', 'paystack'),
    'enabled' => array_values(array_filter(array_map('trim', explode(',', env('PAYMENT_GATEWAYS_ENABLED', 'paystack,opay,monnify'))))),
    'webhook_ip_allowlists' => [
        'paystack' => array_values(array_filter(array_map('trim', explode(',', env('PAYSTACK_WEBHOOK_IPS', ''))))),
        'opay' => array_values(array_filter(array_map('trim', explode(',', env('OPAY_WEBHOOK_IPS', ''))))),
        'monnify' => array_values(array_filter(array_map('trim', explode(',', env('MONNIFY_WEBHOOK_IPS', ''))))),
    ],
    'paystack' => ['secret_key' => env('PAYSTACK_SECRET_KEY'), 'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'), 'base_url' => 'https://api.paystack.co'],
    'opay' => [
        'environment' => env('OPAY_ENVIRONMENT', 'sandbox'), 'merchant_id' => env('OPAY_MERCHANT_ID'),
        'public_key' => env('OPAY_PUBLIC_KEY'), 'private_key' => env('OPAY_PRIVATE_KEY'),
    ],
    'monnify' => [
        'environment' => env('MONNIFY_ENVIRONMENT', 'sandbox'), 'api_key' => env('MONNIFY_API_KEY'),
        'secret_key' => env('MONNIFY_SECRET_KEY'), 'contract_code' => env('MONNIFY_CONTRACT_CODE'),
    ],
];
