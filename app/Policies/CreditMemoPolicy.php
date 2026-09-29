<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CreditMemo;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

/** Credit memos post as they are saved; posted memos are immutable. */
final class CreditMemoPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::CREDITMEMO_MANAGE, RbacRegistry::CREDITMEMO_POST, RbacRegistry::INVOICE_MANAGE];

    protected array $createPermissions = [RbacRegistry::CREDITMEMO_MANAGE, RbacRegistry::CREDITMEMO_POST];

    /** Apply the memo's open balance to the customer's invoices. */
    public function apply(User $user, CreditMemo $memo): bool
    {
        return $this->allowsAll($user, [RbacRegistry::CREDITMEMO_MANAGE], $memo);
    }
}
