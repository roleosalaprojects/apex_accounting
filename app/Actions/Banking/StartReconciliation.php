<?php

declare(strict_types=1);

namespace App\Actions\Banking;

use App\Enums\JournalStatus;
use App\Models\BankAccount;
use App\Models\Reconciliation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Opens a reconciliation and pre-populates it with the bank account's journal
 * lines up to the statement date (manual-assisted matching, §8). Items default
 * to cleared; the user unticks anything still outstanding.
 */
final class StartReconciliation
{
    public function handle(
        BankAccount $bankAccount,
        string $statementDate,
        int $statementEndingBalance,
        ?User $actor = null,
    ): Reconciliation {
        return DB::transaction(function () use ($bankAccount, $statementDate, $statementEndingBalance, $actor): Reconciliation {
            $reconciliation = Reconciliation::query()->create([
                'company_id' => $bankAccount->company_id,
                'bank_account_id' => $bankAccount->id,
                'statement_date' => $statementDate,
                'statement_ending_balance' => $statementEndingBalance,
                'status' => 'in_progress',
                'created_by' => $actor?->id,
            ]);

            $lineIds = DB::table('journal_lines')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.company_id', $bankAccount->company_id)
                ->where('journal_lines.account_id', $bankAccount->account_id)
                ->whereIn('journal_entries.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
                ->whereDate('journal_entries.entry_date', '<=', $statementDate)
                ->pluck('journal_lines.id');

            foreach ($lineIds as $lineId) {
                $reconciliation->items()->create([
                    'journal_line_id' => $lineId,
                    'is_cleared' => true,
                ]);
            }

            return $reconciliation->load('items');
        });
    }
}
