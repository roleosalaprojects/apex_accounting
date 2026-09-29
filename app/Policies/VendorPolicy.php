<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Rbac\RbacRegistry;

final class VendorPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::BILL_MANAGE, RbacRegistry::BILL_POST, RbacRegistry::BILL_PAY, RbacRegistry::PAYMENT_PAY];

    protected array $createPermissions = [RbacRegistry::BILL_MANAGE];

    protected array $managePermissions = [RbacRegistry::BILL_MANAGE];
}
