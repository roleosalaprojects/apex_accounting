<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Rbac\RbacRegistry;

/** Delivery receipts are issued from the sales order and never edited by hand. */
final class DeliveryPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::INVOICE_MANAGE];

    protected array $createPermissions = [];

    protected array $managePermissions = [];
}
