<?php

declare(strict_types=1);

namespace App\Exceptions\Ledger;

use App\Support\Money;

/** An invoice would take the customer's open balance past their credit limit. */
final class CreditLimitException extends LedgerException
{
    public static function make(string $customer, int $outstanding, int $limit, int $invoiceTotal): self
    {
        $peso = fn (int $minor): string => Money::of($minor)->format();

        return new self(sprintf(
            '%s already owes %s against a credit limit of %s; this %s invoice would exceed it by %s. Collect first or raise the limit on the customer.',
            $customer, $peso($outstanding), $peso($limit), $peso($invoiceTotal), $peso($outstanding + $invoiceTotal - $limit),
        ));
    }
}
