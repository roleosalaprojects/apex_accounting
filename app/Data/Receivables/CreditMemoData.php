<?php

declare(strict_types=1);

namespace App\Data\Receivables;

use App\Enums\PricingMode;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class CreditMemoData extends Data
{
    /**
     * @param  DataCollection<int, InvoiceLineData>  $lines
     */
    public function __construct(
        public int $company_id,
        public int $customer_id,
        public string $memo_date,
        #[DataCollectionOf(InvoiceLineData::class)]
        public DataCollection $lines,
        public PricingMode $pricing_mode = PricingMode::VatInclusive,
        public ?string $memo = null,
        public ?int $created_by = null,
        public ?int $approved_by = null,
    ) {}
}
