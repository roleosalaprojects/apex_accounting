<?php

declare(strict_types=1);

namespace App\Exceptions\Ledger;

final class MissingPartnerException extends LedgerException
{
    public static function make(string $detail = ''): self
    {
        $base = 'AR/AP control account requires a partner';

        return new self($detail === '' ? $base : $base.': '.$detail);
    }
}
