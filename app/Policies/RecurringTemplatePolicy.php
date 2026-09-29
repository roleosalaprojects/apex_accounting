<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Support\Rbac\RbacRegistry;

/**
 * Editing a template needs recurring.manage; running due templates needs
 * recurring.run. A run posts under the authority of whoever last saved each
 * template (see RunDueTemplates), so managing templates grants no posting.
 */
final class RecurringTemplatePolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::RECURRING_MANAGE, RbacRegistry::RECURRING_RUN];

    protected array $createPermissions = [RbacRegistry::RECURRING_MANAGE];

    protected array $managePermissions = [RbacRegistry::RECURRING_MANAGE];

    public function runDue(User $user): bool
    {
        return $this->allowsAll($user, [RbacRegistry::RECURRING_RUN]);
    }
}
