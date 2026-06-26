<?php

declare(strict_types=1);

namespace App\Actions\Integration;

use App\Actions\Ledger\CreateDraftJournalEntry;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Enums\PosZReadingStatus;
use App\Models\JournalEntry;
use App\Models\PosZReading;
use App\Models\User;
use App\Services\Integration\PosSalesMapper;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * Imports a staged POS Z-reading (§14): an admin's deliberate choice to bring a
 * day's sales into the books. The reading is mapped to a balanced DRAFT journal
 * entry (no GL effect yet) for final review and posting via the normal approval
 * flow. The staging row is marked imported and linked to the draft so it cannot
 * be imported twice.
 */
final class ImportPosZReading
{
    public function __construct(
        private readonly PosSalesMapper $mapper,
        private readonly CreateDraftJournalEntry $createDraft,
    ) {}

    public function handle(PosZReading $reading, ?User $actor = null): JournalEntry
    {
        return DB::transaction(function () use ($reading, $actor): JournalEntry {
            if (! $reading->isPending()) {
                throw new RuntimeException('Only a pending Z-reading can be imported.');
            }

            $lines = $this->mapper->lines($reading->company_id, $reading->toMapperPayload());

            $draft = $this->createDraft->handle(new JournalEntryData(
                company_id: $reading->company_id,
                entry_date: $reading->business_date->toDateString(),
                memo: 'POS sales'.($reading->reference !== null ? ' — '.$reading->reference : ''),
                lines: new DataCollection(JournalLineData::class, $lines),
                source_type: 'pos.zreading',
                source_id: $reading->id,
                external_reference_no: $reading->reference,
                created_by: $actor?->id,
            ));

            $reading->forceFill([
                'status' => PosZReadingStatus::Imported,
                'journal_entry_id' => $draft->id,
                'imported_by' => $actor?->id,
                'imported_at' => now(),
            ])->save();

            return $draft;
        });
    }
}
