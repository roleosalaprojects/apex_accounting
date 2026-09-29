<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Support\Rbac\RbacRegistry;

/**
 * Chart of accounts: browsable by anyone who prepares or posts entries;
 * changed only with account.manage.
 */
final class AccountPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::ACCOUNT_MANAGE, RbacRegistry::JOURNAL_CREATE, RbacRegistry::JOURNAL_POST];

    protected array $createPermissions = [RbacRegistry::ACCOUNT_MANAGE];

    protected array $managePermissions = [RbacRegistry::ACCOUNT_MANAGE];

    /** Posting the cutover entry also needs journal.post. */
    public function setupOpeningBalances(User $user): bool
    {
        return $this->allowsAll($user, [RbacRegistry::ACCOUNT_MANAGE, RbacRegistry::JOURNAL_POST]);
    }
}
