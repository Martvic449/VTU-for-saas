<?php

namespace App\Services\Providers;

use App\Contracts\Providers\VtuProvider;
use App\Models\Provider;
use InvalidArgumentException;

/** Resolves only explicitly registered adapters; configuration cannot select arbitrary PHP classes. */
final class ProviderAdapterRegistry
{
    /** @var array<string, class-string<VtuProvider>> */
    private array $adapters = [
        'configured_http' => ConfiguredHttpVtuProvider::class,
        'vtpass' => VtpassVtuProvider::class,
        'smeplug' => SmeplugVtuProvider::class,
    ];

    public function for(Provider $provider): VtuProvider
    {
        $class = $this->adapters[$provider->adapter] ?? null;
        if (! $class) throw new InvalidArgumentException('The configured provider adapter is not registered.');
        return new $class($provider);
    }
}
