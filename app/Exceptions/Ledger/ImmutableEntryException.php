<?php

declare(strict_types=1);

namespace App\Exceptions\Ledger;

final class ImmutableEntryException extends LedgerException
{
    public static function make(string $detail = ''): self
    {
        $base = 'Posted journal entries are immutable';

        return new self($detail === '' ? $base : $base.': '.$detail);
    }
}
