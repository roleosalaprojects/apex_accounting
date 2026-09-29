<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Support\Rbac\RbacRegistry;

final class BankAccountPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::BANK_RECORD, RbacRegistry::BANK_RECONCILE];

    protected array $createPermissions = [RbacRegistry::BANK_RECORD];

    protected array $managePermissions = [RbacRegistry::BANK_RECORD];

    /** Deposits, transfers and bank charges. */
    public function recordTransaction(User $user): bool
    {
        return $this->allowsAll($user, [RbacRegistry::BANK_RECORD]);
    }
}
