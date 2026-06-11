<?php

declare(strict_types=1);

namespace App\Data\Banking;

use Spatie\LaravelData\Data;

final class BankChargeData extends Data
{
    public function __construct(
        public int $company_id,
        public int $bank_account_id,     // GL cash/bank account charged
        public int $expense_account_id,
        public string $date,
        public int $amount,
        public ?string $memo = null,
        public ?int $created_by = null,
    ) {}
}
