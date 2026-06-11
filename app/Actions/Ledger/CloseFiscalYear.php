<?php

declare(strict_types=1);

namespace App\Actions\Ledger;

use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Exceptions\Ledger\ClosedPeriodException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\PeriodBalance;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * Year-end close: post a closing JE that zeroes every nominal (income/expense)
 * account into Retained Earnings, then lock all periods of the year (§4.2).
 */
final class CloseFiscalYear
{
    public function __construct(private readonly PostJournalEntry $post) {}

    public function handle(Company $company, int $fiscalYear, ?User $actor = null): ?JournalEntry
    {
        return DB::transaction(function () use ($company, $fiscalYear, $actor): ?JournalEntry {
            $periods = AccountingPeriod::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('fiscal_year', $fiscalYear)
                ->orderBy('period_no')
                ->get();

            if ($periods->isEmpty()) {
                throw ClosedPeriodException::make("fiscal year {$fiscalYear} has no periods");
            }

            /** @var AccountingPeriod $lastPeriod */
            $lastPeriod = $periods->last();
            if ($lastPeriod->status !== PeriodStatus::Open) {
                throw ClosedPeriodException::make('final period must be open to close the year');
            }

            $retainedEarnings = Account::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('subtype', AccountSubtype::RetainedEarnings->value)
                ->first();

            if ($retainedEarnings === null) {
                throw new RuntimeException('No retained earnings (system) account configured.');
            }

            $entry = $this->buildClosingEntry($company, $periods, $lastPeriod, $retainedEarnings, $actor);

            if ($entry !== null) {
                $result = $this->post->handle($entry, $actor);
            } else {
                $result = null;
            }

            AccountingPeriod::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $periods->pluck('id'))
                ->update(['status' => PeriodStatus::Locked->value]);

            return $result;
        });
    }

    /**
     * @param  Collection<int, AccountingPeriod>  $periods
     */
    private function buildClosingEntry(
        Company $company,
        $periods,
        AccountingPeriod $lastPeriod,
        Account $retainedEarnings,
        ?User $actor,
    ): ?JournalEntryData {
        $nominalAccounts = Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('type', [AccountType::Income->value, AccountType::Expense->value])
            ->pluck('id')
            ->all();

        if ($nominalAccounts === []) {
            return null;
        }

        // Year-end balance of each nominal account = its closing in the last period.
        $balances = [];
        PeriodBalance::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('period_id', $lastPeriod->id)
            ->whereIn('account_id', $nominalAccounts)
            ->get()
            ->each(function (PeriodBalance $balance) use (&$balances): void {
                $balances[$balance->account_id] = $balance->closing->minor;
            });

        $lines = [];
        $netSigned = 0; // sum of signed closings (debit-positive)

        foreach ($balances as $accountId => $closing) {
            if ($closing === 0) {
                continue;
            }

            $netSigned += $closing;

            // Post the opposite of the balance to zero it.
            if ($closing > 0) {
                $lines[] = new JournalLineData(
                    account_id: (int) $accountId,
                    credit: $closing,
                    memo: 'Year-end close',
                );
            } else {
                $lines[] = new JournalLineData(
                    account_id: (int) $accountId,
                    debit: -$closing,
                    memo: 'Year-end close',
                );
            }
        }

        if ($lines === []) {
            return null;
        }

        // Net income (credits over debits) = -netSigned. Positive -> profit -> credit RE.
        $netIncome = -$netSigned;
        if ($netIncome >= 0) {
            $lines[] = new JournalLineData(
                account_id: $retainedEarnings->id,
                credit: $netIncome,
                memo: 'Net income to retained earnings',
            );
        } else {
            $lines[] = new JournalLineData(
                account_id: $retainedEarnings->id,
                debit: -$netIncome,
                memo: 'Net loss to retained earnings',
            );
        }

        return new JournalEntryData(
            company_id: $company->id,
            entry_date: $lastPeriod->ends_on->toDateString(),
            memo: "Year-end closing entry FY{$lastPeriod->fiscal_year}",
            lines: new DataCollection(JournalLineData::class, $lines),
            approved_by: $actor?->id,
            created_by: $actor?->id,
        );
    }
}
