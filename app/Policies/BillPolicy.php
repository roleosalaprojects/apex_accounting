<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Rbac\RbacRegistry;

/**
 * Bills post as they are saved, so creating one needs bill.post as well as
 * bill.manage. Posted bills are immutable.
 */
final class BillPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::BILL_MANAGE, RbacRegistry::BILL_POST, RbacRegistry::BILL_PAY, RbacRegistry::PAYMENT_PAY];

    protected array $createPermissions = [RbacRegistry::BILL_MANAGE, RbacRegistry::BILL_POST];
}
