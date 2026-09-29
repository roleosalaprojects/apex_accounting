<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

final class PurchaseOrderPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::BILL_MANAGE];

    protected array $createPermissions = [RbacRegistry::BILL_MANAGE];

    protected array $managePermissions = [RbacRegistry::BILL_MANAGE];

    /** Converting posts the resulting bill. */
    public function convertToBill(User $user, PurchaseOrder $order): bool
    {
        return $this->allowsAll($user, [RbacRegistry::BILL_MANAGE, RbacRegistry::BILL_POST], $order);
    }
}
