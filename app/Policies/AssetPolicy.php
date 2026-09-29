<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

/**
 * Fixed assets. A registered asset is never edited or deleted; it moves
 * through place-in-service, depreciation and disposal.
 */
final class AssetPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::ASSET_MANAGE, RbacRegistry::ASSET_DEPRECIATE, RbacRegistry::ASSET_DISPOSE];

    protected array $createPermissions = [RbacRegistry::ASSET_MANAGE];

    public function placeInService(User $user, Asset $asset): bool
    {
        return $this->allowsAll($user, [RbacRegistry::ASSET_MANAGE], $asset);
    }

    public function dispose(User $user, Asset $asset): bool
    {
        return $this->allowsAll($user, [RbacRegistry::ASSET_DISPOSE], $asset);
    }

    public function runDepreciation(User $user): bool
    {
        return $this->allowsAll($user, [RbacRegistry::ASSET_DEPRECIATE]);
    }
}
