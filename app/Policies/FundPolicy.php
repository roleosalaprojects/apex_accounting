<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Rbac\RbacRegistry;

/** Reporting dimension — maintained alongside the chart of accounts. */
final class FundPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::ACCOUNT_MANAGE];

    protected array $createPermissions = [RbacRegistry::ACCOUNT_MANAGE];

    protected array $managePermissions = [RbacRegistry::ACCOUNT_MANAGE];
}
