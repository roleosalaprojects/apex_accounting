<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Support\Carbon;

/**
 * Statement of Changes in Equity (§12): beginning balance, net change and ending
 * balance for each equity component over the range — the closing balances tie to
 * the Balance Sheet equity section. Profit for the period is shown as a memo
 * (it closes into Retained Earnings at year-end), never summed into the total.
 */
final class StatementOfChangesInEquity
{
    /** Earlier than any posting: "from the beginning". */
    private const DAWN = '1900-01-01';

    public function __construct(
        private readonly ReportBalances $balances,
        private readonly ProfitAndLossReport $profitAndLoss,
    ) {}

    /**
     * @return array{rows: list<array<string, mixed>>, opening_total: int, movement_total: int, closing_total: int, net_income: int}
     */
    public function build(int $companyId, string $from, string $asOf): array
    {
        $perAccount = $this->balances->perAccount($companyId, $from, $asOf);

        $accounts = Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', AccountType::Equity->value)
            ->get();

        $rows = [];
        $openingTotal = 0;
        $movementTotal = 0;
        $closingTotal = 0;

        foreach ($accounts as $account) {
            $b = $perAccount[$account->id] ?? ['opening' => 0, 'debit' => 0, 'credit' => 0, 'ending' => 0];

            // ReportBalances is signed debit-positive; equity is credit-normal.
            $opening = -$b['opening'];
            $closing = -$b['ending'];
            $movement = $closing - $opening;

            if ($opening === 0 && $closing === 0 && $movement === 0) {
                continue;
            }

            $rows[] = [
                'code' => $account->code,
                'name' => $account->name,
                'opening' => $opening,
                'movement' => $movement,
                'closing' => $closing,
            ];

            $openingTotal += $opening;
            $movementTotal += $movement;
            $closingTotal += $closing;
        }

        usort($rows, fn (array $a, array $b): int => $a['code'] <=> $b['code']);

        // Earnings sit in the nominal accounts until a year-end close moves
        // them to Retained Earnings; until then they are still equity, so
        // they get a row of their own and count in the totals, as on the
        // balance sheet.
        $netIncome = $this->profitAndLoss->build($companyId, $from, $asOf)['net_income'];
        $openingEarnings = $this->profitAndLoss->build($companyId, self::DAWN, Carbon::parse($from)->subDay()->toDateString())['net_income'];
        $closingEarnings = $openingEarnings + $netIncome;

        if ($openingEarnings !== 0 || $netIncome !== 0) {
            $rows[] = [
                'code' => null,
                'name' => 'Earnings not yet closed to Retained Earnings',
                'opening' => $openingEarnings,
                'movement' => $netIncome,
                'closing' => $closingEarnings,
            ];
            $openingTotal += $openingEarnings;
            $movementTotal += $netIncome;
            $closingTotal += $closingEarnings;
        }

        return [
            'rows' => $rows,
            'opening_total' => $openingTotal,
            'movement_total' => $movementTotal,
            'closing_total' => $closingTotal,
            'net_income' => $netIncome,
        ];
    }
}
