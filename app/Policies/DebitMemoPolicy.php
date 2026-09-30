<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DebitMemo;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

/** Debit memos post as they are saved, under the same rights as bills; posted memos are immutable. */
final class DebitMemoPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::BILL_MANAGE, RbacRegistry::BILL_POST, RbacRegistry::BILL_PAY, RbacRegistry::PAYMENT_PAY];

    protected array $createPermissions = [RbacRegistry::BILL_MANAGE, RbacRegistry::BILL_POST];

    /** Settle the vendor's bills with the memo's open balance. */
    public function apply(User $user, DebitMemo $memo): bool
    {
        return $this->allowsAll($user, [RbacRegistry::BILL_PAY], $memo);
    }
}
