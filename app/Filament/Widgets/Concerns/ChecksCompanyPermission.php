<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

trait ChecksCompanyPermission
{
    protected static function company(): ?Company
    {
        $company = Filament::getTenant();

        return $company instanceof Company ? $company : null;
    }

    protected static function userMay(string $permission): bool
    {
        $company = static::company();
        $user = Auth::user();

        return $company !== null && $user instanceof User && $user->hasCompanyPermission($company->id, $permission);
    }
}
