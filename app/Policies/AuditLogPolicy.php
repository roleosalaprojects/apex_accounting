<?php

declare(strict_types=1);

namespace App\Policies;

use App\Support\Rbac\RbacRegistry;

/** The audit trail is append-only: readable with audit.view, never changed. */
final class AuditLogPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::AUDIT_VIEW];
}
