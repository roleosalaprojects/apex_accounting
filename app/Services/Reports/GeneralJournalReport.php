<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\JournalStatus;
use App\Models\JournalEntry;

/**
 * General Journal (§12.7): manual / non-special-journal entries (source_type
 * null), columnar with their lines.
 */
final class GeneralJournalReport
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, total_debit: int, total_credit: int}
     */
    public function build(int $companyId, string $from, string $asOf): array
    {
        $entries = JournalEntry::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('source_type')
            ->whereIn('status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
            ->whereDate('entry_date', '>=', $from)
            ->whereDate('entry_date', '<=', $asOf)
            ->with(['lines.account'])
            ->orderBy('entry_date')->get();

        $rows = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as $entry) {
            foreach ($entry->lines as $line) {
                $totalDebit += $line->debit->minor;
                $totalCredit += $line->credit->minor;
                $rows[] = [
                    'date' => $entry->entry_date->toDateString(),
                    'number' => $entry->number,
                    'account' => $line->account?->code.' '.$line->account?->name,
                    'memo' => $line->memo,
                    'debit' => $line->debit->minor,
                    'credit' => $line->credit->minor,
                ];
            }
        }

        return ['rows' => $rows, 'total_debit' => $totalDebit, 'total_credit' => $totalCredit];
    }
}
