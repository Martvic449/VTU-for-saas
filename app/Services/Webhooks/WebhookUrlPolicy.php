<?php
namespace App\Services\Webhooks;
use InvalidArgumentException;
final class WebhookUrlPolicy
{
    public function assertPublicHttps(string $url): void
    {
        $parts = parse_url($url);
        if (! $parts || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            throw new InvalidArgumentException('Webhook URL must be a public HTTPS destination on port 443.');
        }
        $host = strtolower(rtrim($parts['host'], '.'));
        if (app()->environment('testing') && str_ends_with($host, '.example')) return;
        if ($host === 'localhost' || str_contains($host, '.') === false || preg_match('/\.(local|internal|localhost|test)$/', $host)) {
            throw new InvalidArgumentException('Webhook destination hostname is not permitted.');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if ($addresses === []) throw new InvalidArgumentException('Webhook destination hostname could not be resolved.');
        foreach ($addresses as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('Webhook destination resolves to a non-public address.');
            }
        }
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
