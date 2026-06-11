<?php

declare(strict_types=1);

namespace App\Data\Banking;

use Spatie\LaravelData\Data;

final class DepositData extends Data
{
    public function __construct(
        public int $company_id,
        public int $bank_account_id,     // destination GL cash/bank account
        public int $source_account_id,   // e.g. cash on hand, other income
        public string $date,
        public int $amount,
        public ?string $memo = null,
        public ?int $created_by = null,
    ) {}
}
