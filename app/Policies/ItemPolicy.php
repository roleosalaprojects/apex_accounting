<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Item;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

final class ItemPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::INVOICE_MANAGE, RbacRegistry::BILL_MANAGE, RbacRegistry::INVENTORY_ADJUST];

    protected array $createPermissions = [RbacRegistry::INVOICE_MANAGE];

    protected array $managePermissions = [RbacRegistry::INVOICE_MANAGE];

    /** Stock count adjustments post to inventory and the adjustment account. */
    public function adjust(User $user, Item $item): bool
    {
        return $this->allowsAll($user, [RbacRegistry::INVENTORY_ADJUST], $item);
    }
}
