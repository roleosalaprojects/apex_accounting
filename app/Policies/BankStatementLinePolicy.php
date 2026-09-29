<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BankStatementLine;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

/** Statement lines arrive by CSV import, never by hand. */
final class BankStatementLinePolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::BANK_RECONCILE];

    protected array $managePermissions = [RbacRegistry::BANK_RECONCILE];

    public function import(User $user): bool
    {
        return $this->allowsAll($user, [RbacRegistry::BANK_RECONCILE]);
    }

    /** Match, post to the ledger, or ignore a line. */
    public function reconcile(User $user, BankStatementLine $line): bool
    {
        return $this->allowsAll($user, [RbacRegistry::BANK_RECONCILE], $line);
    }
}
