<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Per-company role from the company_user pivot. Role gates on approve/post are
 * enforced in Actions, not just hidden in the UI (§16.10).
 */
enum CompanyRole: string
{
    case Owner = 'owner';
    case Accountant = 'accountant';
    case Bookkeeper = 'bookkeeper';
    case Viewer = 'viewer';

    /** Owner + accountant may approve and post. */
    public function canApprove(): bool
    {
        return $this === self::Owner || $this === self::Accountant;
    }

    public function canPost(): bool
    {
        return $this === self::Owner || $this === self::Accountant;
    }

    /** Bookkeeper+ may create/edit drafts and submit. */
    public function canCreateDraft(): bool
    {
        return $this !== self::Viewer;
    }

    /** Period lock / company settings — owner only. */
    public function canManageCompany(): bool
    {
        return $this === self::Owner;
    }
}
