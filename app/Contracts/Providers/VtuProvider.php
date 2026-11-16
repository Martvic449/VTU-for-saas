<?php

namespace App\Contracts\Providers;

interface VtuProvider
{
    /** @return array{outcome:'successful'|'failed'|'pending', provider_reference?:string, message?:string, data?:array} */
    public function purchase(string $serviceCode, array $payload, string $idempotencyReference): array;

    /** @return array{outcome:'successful'|'failed'|'pending', provider_reference?:string, message?:string, data?:array} */
    public function requery(string $providerReference, string $idempotencyReference): array;
}
