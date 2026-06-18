<?php

declare(strict_types=1);

namespace App\Services\Banking;

use App\Actions\Banking\RecordBankCharge;
use App\Actions\Banking\RecordDeposit;
use App\Data\Banking\BankChargeData;
use App\Data\Banking\DepositData;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * Imports a CSV bank statement into staging lines and posts a chosen line to the
 * ledger (Dr bank / Cr contra for deposits, Dr contra / Cr bank for charges),
 * reusing the existing banking Actions so all posting still goes through the
 * single PostJournalEntry chokepoint.
 */
final class BankStatementImporter
{
    public function __construct(
        private readonly RecordDeposit $recordDeposit,
        private readonly RecordBankCharge $recordBankCharge,
    ) {}

    /**
     * Parse CSV text (header row with date, amount and optional description,
     * reference, balance columns) into unmatched statement lines.
     *
     * @return array{imported: int, skipped: int}
     */
    public function import(BankAccount $bank, string $csv, ?string $importRef = null): array
    {
        $records = array_map(fn (string $line): array => str_getcsv($line), preg_split('/\r\n|\r|\n/', trim($csv)) ?: []);
        $header = array_map(fn ($h): string => strtolower(trim((string) $h)), array_shift($records) ?? []);

        $col = fn (string $name): int|false => array_search($name, $header, true);
        $dateAt = $col('date');
        $amountAt = $col('amount');
        if ($dateAt === false || $amountAt === false) {
            throw new InvalidArgumentException('CSV must include "date" and "amount" columns.');
        }
        $descAt = $col('description');
        $refAt = $col('reference');
        $balanceAt = $col('balance');

        $imported = 0;
        $skipped = 0;

        foreach ($records as $row) {
            if (count(array_filter($row, fn ($c): bool => trim((string) $c) !== '')) === 0) {
                continue; // blank line
            }

            try {
                BankStatementLine::query()->create([
                    'company_id' => $bank->company_id,
                    'bank_account_id' => $bank->id,
                    'txn_date' => Carbon::parse(trim((string) $row[$dateAt]))->toDateString(),
                    'description' => $descAt !== false ? trim((string) ($row[$descAt] ?? '')) : '',
                    'reference' => $refAt !== false && trim((string) ($row[$refAt] ?? '')) !== '' ? trim((string) $row[$refAt]) : null,
                    'amount' => $this->toMinor($row[$amountAt] ?? '0'),
                    'balance' => $balanceAt !== false && trim((string) ($row[$balanceAt] ?? '')) !== '' ? $this->toMinor($row[$balanceAt]) : null,
                    'status' => 'unmatched',
                    'source' => 'csv',
                    'import_ref' => $importRef,
                ]);
                $imported++;
            } catch (Throwable) {
                $skipped++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Post a statement line to the ledger against the chosen contra account and
     * mark it matched. Deposits (amount >= 0) credit the contra; charges debit it.
     */
    public function recordInLedger(BankStatementLine $line, int $contraAccountId, ?User $actor = null): JournalEntry
    {
        /** @var BankAccount $bank */
        $bank = $line->bankAccount()->withoutGlobalScopes()->firstOrFail();
        $amount = abs($line->amount);
        $date = $line->txn_date->toDateString();

        $entry = $line->amount >= 0
            ? $this->recordDeposit->handle(new DepositData(
                company_id: $line->company_id,
                bank_account_id: $bank->account_id,
                source_account_id: $contraAccountId,
                date: $date,
                amount: $amount,
                memo: $line->description,
                created_by: $actor?->id,
            ), $actor)
            : $this->recordBankCharge->handle(new BankChargeData(
                company_id: $line->company_id,
                bank_account_id: $bank->account_id,
                expense_account_id: $contraAccountId,
                date: $date,
                amount: $amount,
                memo: $line->description,
                created_by: $actor?->id,
            ), $actor);

        $line->update(['status' => 'matched', 'journal_entry_id' => $entry->id]);

        return $entry;
    }

    /**
     * Candidate posted entries that could correspond to this statement line: a
     * matching debit/credit on the bank's GL account near the transaction date,
     * not already claimed by another statement line.
     *
     * @return Collection<int, JournalEntry>
     */
    public function suggestMatches(BankStatementLine $line, int $windowDays = 7): Collection
    {
        /** @var BankAccount $bank */
        $bank = $line->bankAccount()->withoutGlobalScopes()->firstOrFail();
        $amount = abs($line->amount);
        $isDeposit = $line->amount >= 0;

        $claimed = BankStatementLine::query()->withoutGlobalScopes()
            ->where('company_id', $line->company_id)
            ->whereNotNull('journal_entry_id')
            ->whereKeyNot($line->id)
            ->pluck('journal_entry_id');

        return JournalEntry::query()->withoutGlobalScopes()
            ->where('company_id', $line->company_id)
            ->whereNotIn('id', $claimed)
            ->whereBetween('entry_date', [
                $line->txn_date->copy()->subDays($windowDays)->toDateString(),
                $line->txn_date->copy()->addDays($windowDays)->toDateString(),
            ])
            ->whereHas('lines', function (Builder $q) use ($bank, $amount, $isDeposit): void {
                $q->where('account_id', $bank->account_id)
                    ->where($isDeposit ? 'debit' : 'credit', $amount);
            })
            ->orderBy('entry_date')
            ->limit(10)
            ->get();
    }

    /** Link a statement line to an existing posted entry (no new posting). */
    public function matchToEntry(BankStatementLine $line, JournalEntry $entry): void
    {
        $line->update(['status' => 'matched', 'journal_entry_id' => $entry->id]);
    }

    private function toMinor(mixed $value): int
    {
        $clean = str_replace([',', ' ', '₱'], '', (string) $value);

        return (int) round(((float) $clean) * 100);
    }
}
