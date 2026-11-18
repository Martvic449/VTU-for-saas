<?php

return [
    'base_domain' => env('TENANT_BASE_DOMAIN', 'localhost'),
    'enforce_known_domains' => env('APP_ENV') === 'production' ? true : (bool) env('TENANT_DOMAIN_ENFORCE', false),
    'platform_hosts' => array_filter(array_map('strtolower', array_map('trim', explode(',', (string) env('TENANT_PLATFORM_HOSTS', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'))))),
    'reserved_slugs' => ['www', 'api', 'admin', 'app', 'support', 'mail', 'status'],
];
