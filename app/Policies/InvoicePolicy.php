<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

/**
 * Anyone who manages invoices may draft one; posting it — straight away, or
 * approving someone's draft — takes invoice.post. Posted invoices are
 * immutable; corrections are voids.
 */
final class InvoicePolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::INVOICE_MANAGE, RbacRegistry::INVOICE_POST, RbacRegistry::INVOICE_VOID, RbacRegistry::PAYMENT_RECEIVE];

    protected array $createPermissions = [RbacRegistry::INVOICE_MANAGE];

    /** Post a new invoice outright, or approve and post a draft. */
    public function post(User $user, Invoice $invoice): bool
    {
        return $this->allowsAll($user, [RbacRegistry::INVOICE_POST], $invoice);
    }

    /** Send a draft back: a poster, or the maker withdrawing their own. */
    public function reject(User $user, Invoice $invoice): bool
    {
        return $this->allowsAll($user, [RbacRegistry::INVOICE_POST], $invoice)
            || ($invoice->created_by === $user->id && $this->allowsAll($user, [RbacRegistry::INVOICE_MANAGE], $invoice));
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $this->allowsAll($user, [RbacRegistry::INVOICE_VOID], $invoice);
    }

    /** Record a foreign-currency collection (and its realized FX gain/loss). */
    public function settleForeignCurrency(User $user, Invoice $invoice): bool
    {
        return $this->allowsAll($user, [RbacRegistry::PAYMENT_RECEIVE], $invoice);
    }
}
