<?php

declare(strict_types=1);

namespace App\Data\Banking;

use Spatie\LaravelData\Data;

final class TransferData extends Data
{
    public function __construct(
        public int $company_id,
        public int $from_account_id,
        public int $to_account_id,
        public string $date,
        public int $amount,
        public ?string $memo = null,
        public ?int $created_by = null,
    ) {}
}
