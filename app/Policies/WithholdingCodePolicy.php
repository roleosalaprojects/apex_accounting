<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Rbac\RbacRegistry;

/** EWT / ATC codes are company settings kept by whoever manages the company. */
final class WithholdingCodePolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::COMPANY_MANAGE];

    protected array $createPermissions = [RbacRegistry::COMPANY_MANAGE];

    protected array $managePermissions = [RbacRegistry::COMPANY_MANAGE];
}
