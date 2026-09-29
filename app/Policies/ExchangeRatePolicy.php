<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Rbac\RbacRegistry;

final class ExchangeRatePolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::ACCOUNT_MANAGE];

    protected array $createPermissions = [RbacRegistry::ACCOUNT_MANAGE];

    protected array $managePermissions = [RbacRegistry::ACCOUNT_MANAGE];
}
