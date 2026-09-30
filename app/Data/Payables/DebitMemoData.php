<?php

declare(strict_types=1);

namespace App\Data\Payables;

use App\Enums\PricingMode;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/** A vendor debit memo: goods returned or a credit the vendor granted, line by line like the bill it reverses. */
final class DebitMemoData extends Data
{
    /**
     * @param  DataCollection<int, BillLineData>  $lines
     */
    public function __construct(
        public int $company_id,
        public int $vendor_id,
        public string $memo_date,
        #[DataCollectionOf(BillLineData::class)]
        public DataCollection $lines,
        public PricingMode $pricing_mode = PricingMode::VatExclusive,
        public ?string $memo = null,
        public ?string $reference_no = null,
        public ?string $external_reference_no = null,
        public ?int $created_by = null,
        public ?int $approved_by = null,
    ) {}
}
