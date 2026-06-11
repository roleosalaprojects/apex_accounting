<?php

declare(strict_types=1);

namespace App\Data\Receivables;

use Spatie\LaravelData\Data;

final class InvoiceLineData extends Data
{
    public function __construct(
        public string $description,
        public string $qty,            // DECIMAL(15,4) as string
        public int $unit_price,        // centavos
        public int $tax_code_id,
        public int $income_account_id,
        public ?int $item_id = null,
        public ?int $department_id = null,
        public ?int $project_id = null,
        public ?int $fund_id = null,
        public ?int $branch_id = null,
    ) {}
}
