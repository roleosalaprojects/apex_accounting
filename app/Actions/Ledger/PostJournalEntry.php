<?php

declare(strict_types=1);

namespace App\Actions\Ledger;

use App\Data\Ledger\JournalEntryData;
use App\Enums\JournalStatus;
use App\Enums\PeriodStatus;
use App\Exceptions\Ledger\ClosedPeriodException;
use App\Exceptions\Ledger\MissingPartnerException;
use App\Exceptions\Ledger\UnapprovedDocumentException;
use App\Exceptions\Ledger\UnbalancedEntryException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Ledger\LedgerBalanceCalculator;
use App\Services\Numbering\NumberGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * THE posting chokepoint (§4.2). Every financial document posts by building a
 * JournalEntryData and calling this Action. Idempotent-safe: it only ever
 * creates a fresh posted entry inside one transaction.
 */
final class PostJournalEntry
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly LedgerBalanceCalculator $balances,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(JournalEntryData $data, ?User $actor = null): JournalEntry
    {
        return DB::transaction(function () use ($data, $actor): JournalEntry {
            /** @var Company $company */
            $company = Company::query()->withoutGlobalScopes()->findOrFail($data->company_id);

            $this->assertActorMayPost($company, $actor);
            $this->assertApprovalSatisfied($company, $data);

            $period = $this->resolveOpenPeriod($company, $data->entry_date);
            $this->validateStructure($data);
            $accounts = $this->loadAndValidateAccounts($company, $data);

            [$totalDebits, $totalCredits] = $this->totals($data);
            if ($totalDebits !== $totalCredits) {
                throw UnbalancedEntryException::make("debits {$totalDebits} != credits {$totalCredits}");
            }

            $this->assertPartnersPresent($data, $accounts);

            $entry = new JournalEntry;
            $entry->forceFill([
                'company_id' => $company->id,
                'period_id' => $period->id,
                'entry_date' => $data->entry_date,
                'memo' => $data->memo,
                'source_type' => $data->source_type,
                'source_id' => $data->source_id,
                'reference_no' => $data->reference_no,
                'external_reference_no' => $data->external_reference_no,
                'remarks' => $data->remarks,
                'reversal_of_id' => $data->reversal_of_id,
                'reversal_reason' => $data->reversal_reason,
                'status' => JournalStatus::Posted,
                'created_by' => $data->created_by ?? $actor?->id,
                'approved_by' => $data->approved_by ?? $actor?->id,
                'approved_at' => now(),
                'posted_by' => $actor?->id,
                'posted_at' => now(),
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
            ]);
            $entry->number = $this->numbers->next(
                $company->id,
                'journal_entry',
                Carbon::parse($data->entry_date)->year,
            );
            $entry->save();

            $lineNo = 1;
            $touchedAccounts = [];
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
                $touchedAccounts[] = $line->account_id;
            }

            $this->balances->persistForAccounts($company->id, $touchedAccounts);

            $this->audit->record(
                $company->id,
                'journal_entry.posted',
                $entry,
                null,
                ['number' => $entry->number, 'total' => $totalDebits],
            );

            return $entry->load('lines');
        });
    }

    private function assertActorMayPost(Company $company, ?User $actor): void
    {
        if ($actor === null) {
            return; // system / API context (scope-gated elsewhere)
        }

        $role = $actor->roleIn($company->id);
        if ($role === null || ! $role->canPost()) {
            throw UnapprovedDocumentException::make('actor lacks posting rights');
        }
    }

    private function assertApprovalSatisfied(Company $company, JournalEntryData $data): void
    {
        if ($company->require_approval && $data->approved_by === null) {
            throw UnapprovedDocumentException::make('company requires approval before posting');
        }
    }

    private function resolveOpenPeriod(Company $company, string $date): AccountingPeriod
    {
        $period = AccountingPeriod::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->containing($date)
            ->first();

        if ($period === null || $period->status !== PeriodStatus::Open) {
            throw ClosedPeriodException::make("no open period for {$date}");
        }

        return $period;
    }

    private function validateStructure(JournalEntryData $data): void
    {
        if ($data->lines->count() < 2) {
            throw UnbalancedEntryException::make('an entry needs at least two lines');
        }

        foreach ($data->lines as $i => $line) {
            $hasDebit = $line->debit > 0;
            $hasCredit = $line->credit > 0;

            if ($line->debit < 0 || $line->credit < 0) {
                throw UnbalancedEntryException::make("line {$i} has a negative amount");
            }
            if ($hasDebit === $hasCredit) {
                throw UnbalancedEntryException::make("line {$i} must have exactly one of debit/credit");
            }
        }
    }

    /**
     * @return array<int, Account>
     */
    private function loadAndValidateAccounts(Company $company, JournalEntryData $data): array
    {
        $ids = array_map(fn ($line) => $line->account_id, iterator_to_array($data->lines));

        $accounts = Account::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($ids as $id) {
            $account = $accounts->get($id);
            if ($account === null || $account->company_id !== $company->id) {
                throw new \InvalidArgumentException("Account {$id} does not belong to this company.");
            }
            if (! $account->is_active) {
                throw new \InvalidArgumentException("Account {$account->code} is inactive.");
            }
        }

        /** @var array<int, Account> $map */
        $map = $accounts->all();

        return $map;
    }

    /**
     * @param  array<int, Account>  $accounts
     */
    private function assertPartnersPresent(JournalEntryData $data, array $accounts): void
    {
        foreach ($data->lines as $line) {
            $account = $accounts[$line->account_id];
            if ($account->isControl() && ($line->partner_type === null || $line->partner_id === null)) {
                throw MissingPartnerException::make("account {$account->code} requires a partner");
            }
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function totals(JournalEntryData $data): array
    {
        $debits = 0;
        $credits = 0;
        foreach ($data->lines as $line) {
            $debits += $line->debit;
            $credits += $line->credit;
        }

        return [$debits, $credits];
    }
}
