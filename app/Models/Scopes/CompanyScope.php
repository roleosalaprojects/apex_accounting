<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope constraining every BelongsToCompany model to the active company.
 * No-op when no company is set in context (seeding / system maintenance).
 *
 * @implements Scope<Model>
 */
final class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = app(CompanyContext::class)->id();

        if ($companyId !== null) {
            $builder->where($model->getTable().'.company_id', $companyId);
        }
    }
}
