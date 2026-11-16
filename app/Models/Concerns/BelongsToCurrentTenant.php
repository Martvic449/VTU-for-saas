<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use RuntimeException;

trait BelongsToCurrentTenant
{
    protected static function bootBelongsToCurrentTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenant = request()->attributes->get('tenant');
            if ($tenant) {
                $builder->where($builder->getModel()->qualifyColumn('tenant_id'), $tenant->id);
            } elseif (! app()->runningInConsole()) {
                throw new RuntimeException('Tenant context is required for this resource.');
            }
        });

        static::creating(function (Model $model): void {
            $tenant = request()->attributes->get('tenant');
            if ($tenant && empty($model->tenant_id)) $model->tenant_id = $tenant->id;
            if (! app()->runningInConsole() && ! $tenant && $model->tenant_id !== null) {
                throw new RuntimeException('Cannot create a tenant-owned resource without a tenant context.');
            }
        });
    }
}
