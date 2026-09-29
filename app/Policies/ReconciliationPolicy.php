<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Reconciliation;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

/** Reconciliations are started and completed through their own actions. */
final class ReconciliationPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::BANK_RECONCILE];

    public function start(User $user): bool
    {
        return $this->allowsAll($user, [RbacRegistry::BANK_RECONCILE]);
    }

    public function complete(User $user, ?Reconciliation $reconciliation = null): bool
    {
        return $this->allowsAll($user, [RbacRegistry::BANK_RECONCILE], $reconciliation);
    }
}
