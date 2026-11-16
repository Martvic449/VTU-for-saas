<?php

namespace App\Console\Commands;

use App\Models\Provider;
use App\Services\Providers\SmeplugReadOnlyClient;
use Illuminate\Console\Command;
use Throwable;

final class InspectSmeplugCatalogue extends Command
{
    protected $signature = 'smeplug:inspect {providerCode} {resource=networks : networks, data-plans, balance or devices}';
    protected $description = 'Read a documented SMEPlug catalogue/account endpoint for an active provider connection';

    public function handle(SmeplugReadOnlyClient $client): int
    {
        $provider = Provider::query()->where('code', $this->argument('providerCode'))->first();
        if (! $provider) { $this->error('SMEPlug provider was not found.'); return self::FAILURE; }
        try {
            $result = $client->get($provider, (string) $this->argument('resource'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
