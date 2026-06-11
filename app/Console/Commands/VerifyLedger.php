<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\PeriodBalance;
use App\Services\Ledger\LedgerBalanceCalculator;
use Illuminate\Console\Command;

/**
 * Integrity gate (§4.1): recompute every balance from journal_lines and assert
 * exact equality with the stored period_balances. Also asserts the global
 * trial balance ties out (signed closings sum to zero).
 */
final class VerifyLedger extends Command
{
    protected $signature = 'ledger:verify {company? : Company id (defaults to all)}';

    protected $description = 'Recompute balances from journal_lines and assert they match period_balances.';

    public function handle(LedgerBalanceCalculator $calculator): int
    {
        $companyArg = $this->argument('company');

        $companies = $companyArg !== null
            ? Company::query()->withoutGlobalScopes()->whereKey($companyArg)->get()
            : Company::query()->withoutGlobalScopes()->get();

        if ($companies->isEmpty()) {
            $this->error('No companies to verify.');

            return self::FAILURE;
        }

        $ok = true;

        foreach ($companies as $company) {
            $ok = $this->verifyCompany($company, $calculator) && $ok;
        }

        if ($ok) {
            $this->info('ledger:verify passed — period_balances match journal_lines and the trial balance ties out.');

            return self::SUCCESS;
        }

        $this->error('ledger:verify FAILED.');

        return self::FAILURE;
    }

    private function verifyCompany(Company $company, LedgerBalanceCalculator $calculator): bool
    {
        $expected = [];
        $tbSigned = 0;
        foreach ($calculator->compute($company->id) as $row) {
            $key = $row['period_id'].':'.$row['account_id'];
            $expected[$key] = $row;
        }

        $stored = [];
        PeriodBalance::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->get()
            ->each(function (PeriodBalance $balance) use (&$stored): void {
                $stored[$balance->period_id.':'.$balance->account_id] = [
                    'opening' => $balance->opening->minor,
                    'debits' => $balance->debits->minor,
                    'credits' => $balance->credits->minor,
                    'closing' => $balance->closing->minor,
                ];
            });

        $ok = true;

        // Compare every expected row against what's stored.
        foreach ($expected as $key => $row) {
            $have = $stored[$key] ?? null;
            if (
                $have === null
                || $have['opening'] !== $row['opening']
                || $have['debits'] !== $row['debits']
                || $have['credits'] !== $row['credits']
                || $have['closing'] !== $row['closing']
            ) {
                $this->error("[{$company->name}] period_balances mismatch at {$key}.");
                $ok = false;
            }
        }

        // Stored rows with no expected counterpart are stale.
        foreach (array_keys($stored) as $key) {
            if (! isset($expected[$key])) {
                $this->error("[{$company->name}] stale period_balances row at {$key}.");
                $ok = false;
            }
        }

        // Trial balance tie-out: latest closing per account must net to zero.
        foreach ($this->latestClosings($expected) as $closing) {
            $tbSigned += $closing;
        }
        if ($tbSigned !== 0) {
            $this->error("[{$company->name}] trial balance does not tie out (signed sum {$tbSigned}).");
            $ok = false;
        }

        return $ok;
    }

    /**
     * @param  array<string, array<string, int>>  $expected
     * @return array<int, int>
     */
    private function latestClosings(array $expected): array
    {
        // Rows are produced in (account, period-order); the last row per account
        // carries its final closing balance.
        $latest = [];
        foreach ($expected as $row) {
            $latest[$row['account_id']] = $row['closing'];
        }

        return $latest;
    }
}
