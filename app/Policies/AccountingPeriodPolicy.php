<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AccountingPeriod;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

/**
 * Periods are created by opening a fiscal year and change only through
 * close/reopen, so there is no create/edit/delete.
 */
final class AccountingPeriodPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::PERIOD_MANAGE, RbacRegistry::PERIOD_CLOSE, RbacRegistry::YEAR_CLOSE];

    public function close(User $user, AccountingPeriod $period): bool
    {
        return $this->allowsAll($user, [RbacRegistry::PERIOD_CLOSE], $period);
    }

    public function reopen(User $user, AccountingPeriod $period): bool
    {
        return $this->allowsAll($user, [RbacRegistry::PERIOD_CLOSE], $period);
    }

    public function openFiscalYear(User $user): bool
    {
        return $this->allowsAll($user, [RbacRegistry::PERIOD_MANAGE]);
    }

    public function closeFiscalYear(User $user): bool
    {
        return $this->allowsAll($user, [RbacRegistry::YEAR_CLOSE]);
    }
}
