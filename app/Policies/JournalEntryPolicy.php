<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

/**
 * The manual-entry screen posts immediately, so creating needs journal.post as
 * well as journal.create. Posted entries are immutable; corrections reverse.
 */
final class JournalEntryPolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::JOURNAL_CREATE, RbacRegistry::JOURNAL_APPROVE, RbacRegistry::JOURNAL_POST, RbacRegistry::JOURNAL_REVERSE];

    protected array $createPermissions = [RbacRegistry::JOURNAL_CREATE, RbacRegistry::JOURNAL_POST];

    /** Approve a draft (POS import, recurring run) and post it. */
    public function approveAndPost(User $user, JournalEntry $entry): bool
    {
        return $this->allowsAll($user, [RbacRegistry::JOURNAL_APPROVE, RbacRegistry::JOURNAL_POST], $entry);
    }

    public function reverse(User $user, JournalEntry $entry): bool
    {
        return $this->allowsAll($user, [RbacRegistry::JOURNAL_REVERSE], $entry);
    }
}
