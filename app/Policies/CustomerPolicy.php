<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Rbac\RbacRegistry;

final class CustomerPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::INVOICE_MANAGE, RbacRegistry::INVOICE_POST, RbacRegistry::CREDITMEMO_MANAGE, RbacRegistry::PAYMENT_RECEIVE];

    protected array $createPermissions = [RbacRegistry::INVOICE_MANAGE];

    protected array $managePermissions = [RbacRegistry::INVOICE_MANAGE];
}
