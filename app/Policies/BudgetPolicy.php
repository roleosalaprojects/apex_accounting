<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Rbac\RbacRegistry;

final class BudgetPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::BUDGET_MANAGE];

    protected array $createPermissions = [RbacRegistry::BUDGET_MANAGE];

    protected array $managePermissions = [RbacRegistry::BUDGET_MANAGE];
}
