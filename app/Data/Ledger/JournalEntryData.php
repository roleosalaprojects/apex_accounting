<?php

declare(strict_types=1);

namespace App\Data\Ledger;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class JournalEntryData extends Data
{
    /**
     * @param  DataCollection<int, JournalLineData>  $lines
     */
    public function __construct(
        public int $company_id,
        public string $entry_date,
        public ?string $memo = null,
        #[DataCollectionOf(JournalLineData::class)]
        public DataCollection $lines = new DataCollection(JournalLineData::class, []),
        public ?string $source_type = null,
        public ?int $source_id = null,
        public ?string $reference_no = null,
        public ?string $external_reference_no = null,
        public ?string $remarks = null,
        // Set when the document has already passed approval; lets the posting
        // chokepoint honour require_approval without a separate workflow table.
        public ?int $approved_by = null,
        public ?int $created_by = null,
        // Reversal linkage — set when this entry reverses another (§4.2).
        public ?int $reversal_of_id = null,
        public ?string $reversal_reason = null,
    ) {}
}
