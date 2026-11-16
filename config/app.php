<?php

return [
    'name' => env('APP_NAME', 'VTU SaaS'), 'env' => env('APP_ENV', 'production'), 'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'), 'timezone' => env('APP_TIMEZONE', 'Africa/Lagos'), 'locale' => env('APP_LOCALE', 'en'),
    'legal_entity' => env('LEGAL_ENTITY_NAME', '[INSERT REGISTERED LEGAL BUSINESS NAME]'),
    'legal_address' => env('LEGAL_ENTITY_ADDRESS', '[INSERT REGISTERED BUSINESS ADDRESS]'),
    'support_email' => env('SUPPORT_EMAIL', 'support@example.com'),
    'privacy_email' => env('PRIVACY_EMAIL', 'privacy@example.com'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'), 'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),
    'cipher' => 'AES-256-CBC', 'key' => env('APP_KEY'), 'previous_keys' => array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),
    'maintenance' => ['driver' => env('APP_MAINTENANCE_DRIVER', 'file'), 'store' => env('APP_MAINTENANCE_STORE', 'database')],
];
