<?php

declare(strict_types=1);

namespace App\Exceptions\Ledger;

final class InvalidVatBucketException extends LedgerException
{
    public static function make(string $detail = ''): self
    {
        $base = 'Invalid or missing VAT bucket';

        return new self($detail === '' ? $base : $base.': '.$detail);
    }
}
