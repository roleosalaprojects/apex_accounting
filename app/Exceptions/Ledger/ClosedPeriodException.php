<?php

declare(strict_types=1);

namespace App\Exceptions\Ledger;

final class ClosedPeriodException extends LedgerException
{
    public static function make(string $detail = ''): self
    {
        $base = 'Accounting period is not open for posting';

        return new self($detail === '' ? $base : $base.': '.$detail);
    }
}
