<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Rbac\RbacRegistry;

/** Depreciation entries are posted by the monthly run and only ever read. */
final class DepreciationEntryPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::ASSET_MANAGE, RbacRegistry::ASSET_DEPRECIATE, RbacRegistry::ASSET_DISPOSE];

    protected array $createPermissions = [];

    protected array $managePermissions = [];
}
