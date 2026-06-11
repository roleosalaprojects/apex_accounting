<?php

declare(strict_types=1);

namespace App\Data\Payables;

use App\Enums\VatBucket;
use Spatie\LaravelData\Data;

final class BillLineData extends Data
{
    public function __construct(
        public string $description,
        public string $qty,
        public int $unit_price,
        public int $tax_code_id,
        public int $expense_or_asset_account_id,
        public ?VatBucket $vat_bucket = null,
        public ?int $item_id = null,
        public ?int $department_id = null,
        public ?int $project_id = null,
        public ?int $fund_id = null,
        public ?int $branch_id = null,
    ) {}
}
