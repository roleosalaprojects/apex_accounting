<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Company;
use Illuminate\Support\Carbon;

/**
 * Balance Sheet as of a date (§12.4), including the current-year earnings line
 * before close. Must tie out: assets == liabilities + equity.
 */
final class BalanceSheetReport
{
    /** Earlier than any posting: "from the beginning". */
    private const DAWN = '1900-01-01';

    public function __construct(
        private readonly ReportBalances $balances,
        private readonly ProfitAndLossReport $pnl,
    ) {}

    /**
     * Equity carries two synthetic lines besides the equity accounts: the
     * current fiscal year's earnings, and the earnings of earlier years that
     * have not been closed into Retained Earnings yet (nil once every year is
     * closed, since closing entries zero the nominal accounts).
     *
     * @return array{assets: array<int, array<string, mixed>>, liabilities: array<int, array<string, mixed>>, equity: array<int, array<string, mixed>>, total_assets: int, total_liabilities: int, total_equity: int, current_year_earnings: int, prior_years_earnings: int, balanced: bool}
     */
    public function build(Company $company, string $asOf): array
    {
        $perAccount = $this->balances->perAccount($company->id, null, $asOf);
        $accounts = Account::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->get()->keyBy('id');

        $assets = [];
        $liabilities = [];
        $equity = [];
        $totalAssets = 0;
        $totalLiabilities = 0;
        $totalEquity = 0;

        foreach ($perAccount as $row) {
            $account = $accounts->get($row['account_id']);
            if ($account === null || $row['ending'] === 0) {
                continue;
            }

            $signed = $row['ending'];
            $line = ['account_id' => $account->id, 'code' => $account->code, 'name' => $account->name];

            match ($account->type) {
                AccountType::Asset => [$assets[] = $line + ['amount' => $signed], $totalAssets += $signed],
                AccountType::Liability => [$liabilities[] = $line + ['amount' => -$signed], $totalLiabilities += -$signed],
                AccountType::Equity => [$equity[] = $line + ['amount' => -$signed], $totalEquity += -$signed],
                default => null, // nominal accounts roll into current-year earnings
            };
        }

        $fiscalYearStart = $this->fiscalYearStart($company, $asOf);
        $earnings = $this->pnl->build($company->id, $fiscalYearStart, $asOf)['net_income'];
        $equity[] = ['account_id' => null, 'code' => null, 'name' => 'Current-year earnings', 'amount' => $earnings];
        $totalEquity += $earnings;

        $priorEarnings = $this->pnl->build($company->id, self::DAWN, Carbon::parse($fiscalYearStart)->subDay()->toDateString())['net_income'];
        if ($priorEarnings !== 0) {
            $equity[] = ['account_id' => null, 'code' => null, 'name' => 'Retained earnings — prior years not yet closed', 'amount' => $priorEarnings];
            $totalEquity += $priorEarnings;
        }

        $byCode = fn ($a, $b) => ($a['code'] ?? 'zzz') <=> ($b['code'] ?? 'zzz');
        usort($assets, $byCode);
        usort($liabilities, $byCode);
        usort($equity, $byCode);

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'current_year_earnings' => $earnings,
            'prior_years_earnings' => $priorEarnings,
            'balanced' => $totalAssets === $totalLiabilities + $totalEquity,
        ];
    }

    private function fiscalYearStart(Company $company, string $asOf): string
    {
        $date = Carbon::parse($asOf);
        $startMonth = $company->fiscal_year_start_month;
        $year = $date->month >= $startMonth ? $date->year : $date->year - 1;

        return Carbon::create($year, $startMonth, 1)->toDateString();
    }
}
