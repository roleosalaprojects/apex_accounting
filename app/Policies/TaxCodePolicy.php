<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Rbac\RbacRegistry;

/** VAT codes are company settings: posting keys on VAT12 / EXEMPT / ZERO, so they are renamed and re-rated, never added or removed. */
final class TaxCodePolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::COMPANY_MANAGE];

    protected array $createPermissions = [];

    protected array $managePermissions = [RbacRegistry::COMPANY_MANAGE];
}
