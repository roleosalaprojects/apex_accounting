<?php

declare(strict_types=1);

namespace App\Exceptions\Ledger;

final class NegativeInventoryException extends LedgerException
{
    public static function make(string $detail = ''): self
    {
        $base = 'Operation would drive inventory negative';

        return new self($detail === '' ? $base : $base.': '.$detail);
    }
}
