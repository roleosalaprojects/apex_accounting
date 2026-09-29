<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Rbac\RbacRegistry;

/** Security log: readable with audit.view, never changed. */
final class LoginEventPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::AUDIT_VIEW];
}
