<?php

declare(strict_types=1);

namespace App\Data\Ledger;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class OpeningBalancesData extends Data
{
    /**
     * @param  DataCollection<int, JournalLineData>  $lines  Per-account opening balances (debit/credit centavos).
     */
    public function __construct(
        public int $company_id,
        public string $opening_date,
        #[DataCollectionOf(JournalLineData::class)]
        public DataCollection $lines,
        public ?int $created_by = null,
    ) {}
}
