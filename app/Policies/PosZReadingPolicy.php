<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PosZReading;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

/**
 * The POS staging inbox. Readings arrive from the API; importing one only
 * creates a draft entry, so journal.create is enough to work the inbox.
 */
final class PosZReadingPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::JOURNAL_CREATE];

    protected array $managePermissions = [RbacRegistry::JOURNAL_CREATE];

    /** Import, dismiss or restore readings (singly or in bulk). */
    public function import(User $user, ?PosZReading $reading = null): bool
    {
        return $this->allowsAll($user, [RbacRegistry::JOURNAL_CREATE], $reading);
    }
}
