<?php

declare(strict_types=1);

namespace App\Data\Ledger;

use App\Enums\VatBucket;
use Spatie\LaravelData\Data;

final class JournalLineData extends Data
{
    public function __construct(
        public int $account_id,
        public int $debit = 0,
        public int $credit = 0,
        public ?string $memo = null,
        public ?string $partner_type = null,
        public ?int $partner_id = null,
        public ?int $tax_code_id = null,
        public ?VatBucket $vat_bucket = null,
        public ?int $department_id = null,
        public ?int $project_id = null,
        public ?int $fund_id = null,
        public ?int $branch_id = null,
    ) {}
}
