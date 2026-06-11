<?php

declare(strict_types=1);

namespace App\Actions\Ledger;

use App\Data\Ledger\JournalEntryData;
use App\Enums\JournalStatus;
use App\Exceptions\Ledger\ClosedPeriodException;
use App\Exceptions\Ledger\UnbalancedEntryException;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

/**
 * Persists a balanced DRAFT journal entry (no GL effect) for later review and
 * posting — used by the approval flow and by recurring templates with
 * auto_post = false. Posting still goes through PostJournalEntry (§4.2).
 */
final class CreateDraftJournalEntry
{
    public function handle(JournalEntryData $data): JournalEntry
    {
        return DB::transaction(function () use ($data): JournalEntry {
            $debits = 0;
            $credits = 0;
            foreach ($data->lines as $line) {
                $debits += $line->debit;
                $credits += $line->credit;
            }
            if ($data->lines->count() < 2 || $debits !== $credits) {
                throw UnbalancedEntryException::make('draft entry is unbalanced');
            }

            $period = AccountingPeriod::query()
                ->withoutGlobalScopes()
                ->where('company_id', $data->company_id)
                ->containing($data->entry_date)
                ->first();

            if ($period === null) {
                throw ClosedPeriodException::make("no period for {$data->entry_date}");
            }

            $entry = new JournalEntry;
            $entry->forceFill([
                'company_id' => $data->company_id,
                'period_id' => $period->id,
                'entry_date' => $data->entry_date,
                'memo' => $data->memo,
                'source_type' => $data->source_type,
                'source_id' => $data->source_id,
                'status' => JournalStatus::Draft,
                'reference_no' => $data->reference_no,
                'external_reference_no' => $data->external_reference_no,
                'remarks' => $data->remarks,
                'created_by' => $data->created_by,
                'total_debits' => $debits,
                'total_credits' => $credits,
            ]);
            $entry->save();

            $lineNo = 1;
            foreach ($data->lines as $line) {
                $entry->lines()->create([
                    'line_no' => $lineNo++,
                    'account_id' => $line->account_id,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'memo' => $line->memo,
                    'partner_type' => $line->partner_type,
                    'partner_id' => $line->partner_id,
                    'tax_code_id' => $line->tax_code_id,
                    'vat_bucket' => $line->vat_bucket,
                    'department_id' => $line->department_id,
                    'project_id' => $line->project_id,
                    'fund_id' => $line->fund_id,
                    'branch_id' => $line->branch_id,
                ]);
            }

            return $entry->load('lines');
        });
    }
}
