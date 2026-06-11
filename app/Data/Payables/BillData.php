<?php

declare(strict_types=1);

namespace App\Data\Payables;

use App\Enums\PricingMode;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class BillData extends Data
{
    /**
     * @param  DataCollection<int, BillLineData>  $lines
     */
    public function __construct(
        public int $company_id,
        public int $vendor_id,
        public string $bill_date,
        #[DataCollectionOf(BillLineData::class)]
        public DataCollection $lines,
        public ?string $due_date = null,
        public PricingMode $pricing_mode = PricingMode::VatExclusive,
        public bool $is_opening = false,
        public ?string $memo = null,
        public ?string $reference_no = null,
        public ?string $external_reference_no = null,
        public ?string $remarks = null,
        public ?int $department_id = null,
        public ?int $project_id = null,
        public ?int $fund_id = null,
        public ?int $branch_id = null,
        public ?int $created_by = null,
        public ?int $approved_by = null,
    ) {}
}
