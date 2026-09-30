<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\VendorPayment;
use App\Support\Rbac\RbacRegistry;

/** Bill payments post as they are saved; posted payments are immutable. */
final class VendorPaymentPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::BILL_PAY, RbacRegistry::PAYMENT_PAY, RbacRegistry::BILL_MANAGE];

    protected array $createPermissions = [RbacRegistry::BILL_PAY];

    /** Reverse a payment; the same right as making one. */
    public function void(User $user, VendorPayment $record): bool
    {
        return $this->allowsAll($user, [RbacRegistry::BILL_PAY], $record);
    }
}
