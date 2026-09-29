<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Rbac\RbacRegistry;

final class TaxReturnPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::TAX_RETURNS_MANAGE];

    protected array $createPermissions = [RbacRegistry::TAX_RETURNS_MANAGE];

    protected array $managePermissions = [RbacRegistry::TAX_RETURNS_MANAGE];
}
