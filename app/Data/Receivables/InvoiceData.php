<?php

declare(strict_types=1);

namespace App\Data\Receivables;

use App\Enums\PricingMode;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class InvoiceData extends Data
{
    /**
     * @param  DataCollection<int, InvoiceLineData>  $lines
     */
    public function __construct(
        public int $company_id,
        public int $customer_id,
        public string $invoice_date,
        #[DataCollectionOf(InvoiceLineData::class)]
        public DataCollection $lines,
        public ?string $due_date = null,
        public PricingMode $pricing_mode = PricingMode::VatInclusive,
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
