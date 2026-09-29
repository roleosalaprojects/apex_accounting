<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Rbac\RbacRegistry;

final class AssetCategoryPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::ASSET_MANAGE];

    protected array $createPermissions = [RbacRegistry::ASSET_MANAGE];

    protected array $managePermissions = [RbacRegistry::ASSET_MANAGE];
}
