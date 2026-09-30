<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerPayment;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

/** Receipts post as they are saved; posted receipts are immutable. */
final class CustomerPaymentPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::PAYMENT_RECEIVE, RbacRegistry::INVOICE_MANAGE];

    protected array $createPermissions = [RbacRegistry::PAYMENT_RECEIVE];

    /** Reverse a collection; the same right as receiving one. */
    public function void(User $user, CustomerPayment $record): bool
    {
        return $this->allowsAll($user, [RbacRegistry::PAYMENT_RECEIVE], $record);
    }
}
