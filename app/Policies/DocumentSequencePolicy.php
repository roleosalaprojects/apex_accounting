<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Rbac\RbacRegistry;

/** Document number series are company settings: no creating or deleting, only tuning. */
final class DocumentSequencePolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::COMPANY_MANAGE];

    protected array $createPermissions = [];

    protected array $managePermissions = [RbacRegistry::COMPANY_MANAGE];
}
