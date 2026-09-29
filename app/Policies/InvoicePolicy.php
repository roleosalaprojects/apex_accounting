<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;

/**
 * Invoices post as they are saved, so creating one needs invoice.post as well
 * as invoice.manage. Posted invoices are immutable; corrections are voids.
 */
final class InvoicePolicy extends PermissionPolicy
{
    protected array $viewPermissions = [RbacRegistry::INVOICE_MANAGE, RbacRegistry::INVOICE_POST, RbacRegistry::INVOICE_VOID, RbacRegistry::PAYMENT_RECEIVE];

    protected array $createPermissions = [RbacRegistry::INVOICE_MANAGE, RbacRegistry::INVOICE_POST];

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
