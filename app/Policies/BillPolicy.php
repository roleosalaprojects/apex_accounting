<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Bill;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

/**
 * Anyone who manages bills may draft one; posting it — straight away, or
 * approving someone's draft — takes bill.post. Posted bills are immutable.
 */
final class BillPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::BILL_MANAGE, RbacRegistry::BILL_POST, RbacRegistry::BILL_PAY, RbacRegistry::PAYMENT_PAY];

    protected array $createPermissions = [RbacRegistry::BILL_MANAGE];

    /** Post a new bill outright, or approve and post a draft. */
    public function post(User $user, Bill $bill): bool
    {
        return $this->allowsAll($user, [RbacRegistry::BILL_POST], $bill);
    }

    /** Send a draft back: a poster, or the maker withdrawing their own. */
    public function reject(User $user, Bill $bill): bool
    {
        return $this->allowsAll($user, [RbacRegistry::BILL_POST], $bill)
            || ($bill->created_by === $user->id && $this->allowsAll($user, [RbacRegistry::BILL_MANAGE], $bill));
    }

    /** Record a payment against the bill. */
    public function pay(User $user, Bill $bill): bool
    {
        return $this->allowsAll($user, [RbacRegistry::BILL_PAY], $bill);
    }

    /** Reverse a posted bill; the same right as posting one. */
    public function void(User $user, Bill $record): bool
    {
        return $this->allowsAll($user, [RbacRegistry::BILL_POST], $record);
    }
}
