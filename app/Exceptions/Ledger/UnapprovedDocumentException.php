<?php

declare(strict_types=1);

namespace App\Exceptions\Ledger;

final class UnapprovedDocumentException extends LedgerException
{
    public static function make(string $detail = ''): self
    {
        $base = 'Document is not approved for posting';

        return new self($detail === '' ? $base : $base.': '.$detail);
    }
}
