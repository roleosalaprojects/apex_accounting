<?php

declare(strict_types=1);

namespace App\Exceptions\Ledger;

final class UnbalancedEntryException extends LedgerException
{
    public static function make(string $detail = ''): self
    {
        $base = 'Journal entry debits and credits are not equal';

        return new self($detail === '' ? $base : $base.': '.$detail);
    }
}
