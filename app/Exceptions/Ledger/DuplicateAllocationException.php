<?php

declare(strict_types=1);

namespace App\Exceptions\Ledger;

final class DuplicateAllocationException extends LedgerException
{
    public static function make(string $detail = ''): self
    {
        $base = 'Allocation already exists for this period';

        return new self($detail === '' ? $base : $base.': '.$detail);
    }
}
