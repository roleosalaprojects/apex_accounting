<?php

declare(strict_types=1);

namespace App\Actions\Ledger;

use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Enums\JournalStatus;
use App\Exceptions\Ledger\ImmutableEntryException;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\DataCollection;

/**
 * Approves and posts a DRAFT entry (§4.2 approval flow): the draft's lines are
 * re-validated through the PostJournalEntry chokepoint (balance, period,
 * partner rules, gapless numbering) and the draft shell is removed.
 */
final class PostDraftJournalEntry
{
    public function __construct(private readonly PostJournalEntry $post) {}

    public function handle(JournalEntry $draft, ?User $actor = null): JournalEntry
    {
        if ($draft->status !== JournalStatus::Draft) {
            throw ImmutableEntryException::make('only a draft entry can be approved and posted');
        }

        return DB::transaction(function () use ($draft, $actor): JournalEntry {
            $draft->loadMissing('lines');

            $lines = $draft->lines->sortBy('line_no')->values()
                ->map(fn (JournalLine $l): JournalLineData => new JournalLineData(
                    account_id: $l->account_id,
                    debit: $l->debit->minor,
                    credit: $l->credit->minor,
                    memo: $l->memo,
                    partner_type: $l->partner_type,
                    partner_id: $l->partner_id,
                    tax_code_id: $l->tax_code_id,
                    vat_bucket: $l->vat_bucket,
                    department_id: $l->department_id,
                    project_id: $l->project_id,
                    fund_id: $l->fund_id,
                    branch_id: $l->branch_id,
                ))->all();

            $posted = $this->post->handle(new JournalEntryData(
                company_id: $draft->company_id,
                entry_date: $draft->entry_date->toDateString(),
                memo: $draft->memo,
                lines: new DataCollection(JournalLineData::class, $lines),
                source_type: $draft->source_type,
                source_id: $draft->source_id,
                reference_no: $draft->reference_no,
                external_reference_no: $draft->external_reference_no,
                remarks: $draft->remarks,
                approved_by: $actor?->id,
                created_by: $draft->created_by,
            ), $actor);

            $draft->lines()->delete();
            $draft->delete();

            return $posted;
        });
    }
}
