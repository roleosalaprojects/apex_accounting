<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

final class SalesOrderPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::INVOICE_MANAGE];

    protected array $createPermissions = [RbacRegistry::INVOICE_MANAGE];

    protected array $managePermissions = [RbacRegistry::INVOICE_MANAGE];

    /** Converting posts the resulting invoice. */
    public function convertToInvoice(User $user, SalesOrder $order): bool
    {
        return $this->allowsAll($user, [RbacRegistry::INVOICE_MANAGE, RbacRegistry::INVOICE_POST], $order);
    }
}
