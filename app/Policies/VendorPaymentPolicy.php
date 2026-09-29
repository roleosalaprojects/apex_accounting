<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Rbac\RbacRegistry;

/** Bill payments post as they are saved; posted payments are immutable. */
final class VendorPaymentPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::BILL_PAY, RbacRegistry::PAYMENT_PAY, RbacRegistry::BILL_MANAGE];

    protected array $createPermissions = [RbacRegistry::BILL_PAY];
}
