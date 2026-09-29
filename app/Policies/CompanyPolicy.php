<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

/**
 * The tenant itself. Filament asks `create` for company registration and
 * `update` for the company settings page.
 */
final class CompanyPolicy
{
    /** Any signed-in user may register a new company; they become its owner. */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasCompanyPermission($company->id, RbacRegistry::COMPANY_MANAGE);
    }
}
