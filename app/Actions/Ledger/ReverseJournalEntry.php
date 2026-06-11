<?php

declare(strict_types=1);

namespace App\Actions\Ledger;

use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Enums\JournalStatus;
use App\Enums\PeriodStatus;
use App\Exceptions\Ledger\ImmutableEntryException;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\DataCollection;

/**
 * Corrections = reversal + new entry (§16.1). Creates a new posted entry with
 * debits/credits swapped, links both directions, and flips the original to
 * `reversed`. A reason is mandatory and persisted to reversal_reason + audit.
 */
final class ReverseJournalEntry
{
    public function __construct(
        private readonly PostJournalEntry $post,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(
        JournalEntry $entry,
        string $reason,
        ?string $reversalDate = null,
        ?User $actor = null,
    ): JournalEntry {
        $reason = trim($reason);
        if ($reason === '') {
            throw ImmutableEntryException::make('a reversal reason is required');
        }

        if ($entry->status !== JournalStatus::Posted) {
            throw ImmutableEntryException::make('only posted entries can be reversed');
        }

        return DB::transaction(function () use ($entry, $reason, $reversalDate, $actor): JournalEntry {
            $entry->loadMissing('lines');

            $date = $this->resolveReversalDate($entry, $reversalDate);

            $lines = $entry->lines
                ->sortBy('line_no')
                ->map(fn ($line): JournalLineData => new JournalLineData(
                    account_id: $line->account_id,
                    debit: $line->credit->minor, // swapped
                    credit: $line->debit->minor,
                    memo: 'Reversal: '.(string) $line->memo,
                    partner_type: $line->partner_type,
                    partner_id: $line->partner_id,
                    tax_code_id: $line->tax_code_id,
                    vat_bucket: $line->vat_bucket,
                    department_id: $line->department_id,
                    project_id: $line->project_id,
                    fund_id: $line->fund_id,
                    branch_id: $line->branch_id,
                ))
                ->values()
                ->all();

            $actorId = $actor?->id;

            $data = new JournalEntryData(
                company_id: $entry->company_id,
                entry_date: $date,
                memo: 'Reversal of '.(string) $entry->number,
                lines: new DataCollection(JournalLineData::class, $lines),
                source_type: $entry->source_type,
                source_id: $entry->source_id,
                approved_by: $actorId ?? $entry->approved_by,
                created_by: $actorId ?? $entry->created_by,
                reversal_of_id: $entry->id,
                reversal_reason: $reason,
            );

            $reversing = $this->post->handle($data, $actor);

            // Allowed post-status mutations: reversal linkage + status flip.
            $entry->forceFill([
                'status' => JournalStatus::Reversed,
                'reversed_by_id' => $reversing->id,
            ])->save();

            $this->audit->record(
                $entry->company_id,
                'journal_entry.reversed',
                $entry,
                ['number' => $entry->number],
                ['reversed_by' => $reversing->number],
                $reason,
            );

            return $reversing;
        });
    }

    private function resolveReversalDate(JournalEntry $entry, ?string $reversalDate): string
    {
        if ($reversalDate !== null) {
            return $reversalDate;
        }

        $originalDate = $entry->entry_date->toDateString();

        $originalPeriod = AccountingPeriod::query()
            ->withoutGlobalScopes()
            ->where('company_id', $entry->company_id)
            ->containing($originalDate)
            ->first();

        if ($originalPeriod !== null && $originalPeriod->status === PeriodStatus::Open) {
            return $originalDate;
        }

        $firstOpen = AccountingPeriod::query()
            ->withoutGlobalScopes()
            ->where('company_id', $entry->company_id)
            ->where('status', PeriodStatus::Open)
            ->orderBy('fiscal_year')
            ->orderBy('period_no')
            ->first();

        return $firstOpen !== null
            ? Carbon::parse($firstOpen->starts_on)->toDateString()
            : $originalDate;
    }
}
