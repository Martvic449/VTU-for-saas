<?php

namespace App\Services\Webhooks;

use InvalidArgumentException;

final class WebhookUrlPolicy
{
    /**
     * Validate a webhook/provider URL and return cURL options that pin the
     * request to the public address checked here. This closes the DNS-rebinding
     * gap between validation and the subsequent HTTP connection.
     *
     * @return array<string, array<int, mixed>>
     */
    public function assertPublicHttps(string $url): array
    {
        $parts = parse_url($url);
        $requestHost = strtolower((string) ($parts['host'] ?? ''));
        $host = rtrim($requestHost, '.');
        $port = (int) ($parts['port'] ?? 443);

        if (! $parts || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || $requestHost === '' || $requestHost !== $host
            || isset($parts['user']) || isset($parts['pass'])
            || $port !== 443) {
            throw new InvalidArgumentException('Webhook URL must be a public HTTPS destination on port 443.');
        }

        if ($host === 'localhost' || str_contains($host, '.') === false || preg_match('/\.(local|internal|localhost|test)$/', $host)) {
            throw new InvalidArgumentException('Webhook destination hostname is not permitted.');
        }

        if (app()->environment('testing') && str_ends_with($host, '.example')) {
            return [];
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if ($addresses === []) {
            throw new InvalidArgumentException('Webhook destination hostname could not be resolved.');
        }

        return $this->pinnedResolveOptions($requestHost, $port, $addresses);
    }

    /**
     * @param array<int, string> $addresses
     * @return array<string, array<int, mixed>>
     */
    public function pinnedResolveOptions(string $host, int $port, array $addresses): array
    {
        if ($addresses === []) {
            throw new InvalidArgumentException('Webhook destination hostname could not be resolved.');
        }

        foreach ($addresses as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('Webhook destination resolves to a non-public address.');
            }
        }

        // IP-literal URLs are already pinned by their URL; hostname URLs must
        // be pinned to the first address that passed the public-IP checks.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [];
        }

        if (! extension_loaded('curl') || ! defined('CURLOPT_RESOLVE')) {
            throw new InvalidArgumentException('Secure webhook delivery requires the PHP cURL extension.');
        }

        $address = $addresses[0];
        if (str_contains($address, ':')) {
            $address = '['.$address.']';
        }

        return ['curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.$address]]];
    }

    private function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $ips = [];
        foreach ($records as $record) {
            if (! empty($record['ip'])) $ips[] = $record['ip'];
            if (! empty($record['ipv6'])) $ips[] = $record['ipv6'];
        }

        return array_values(array_unique($ips));
    }
}
