<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AccountSubtype;
use App\Models\Account;

/**
 * Cash Flow, indirect method (§12.5). Balance-change form: each non-cash
 * account's signed change is converted to its cash impact (−Δ) and assigned to
 * a section via CashFlowMap. The section totals sum to the actual change in
 * cash/bank — the tie-out.
 */
final class CashFlowReport
{
    public function __construct(
        private readonly ReportBalances $balances,
        private readonly CashFlowMap $map,
    ) {}

    /**
     * @return array{operating: int, investing: int, financing: int, net_change: int, cash_change: int, balanced: bool}
     */
    public function build(int $companyId, string $from, string $asOf): array
    {
        $movement = $this->balances->movementBetween($companyId, $from, $asOf);
        $accounts = Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->get()->keyBy('id');

        $sections = [CashFlowMap::OPERATING => 0, CashFlowMap::INVESTING => 0, CashFlowMap::FINANCING => 0];
        $cashChange = 0;

        foreach ($movement as $accountId => $m) {
            $account = $accounts->get($accountId);
            if ($account === null) {
                continue;
            }

            $delta = $m['debit'] - $m['credit']; // signed change

            if (in_array($account->subtype, [AccountSubtype::Cash, AccountSubtype::Bank], true)) {
                $cashChange += $delta;

                continue;
            }

            $section = $this->map->sectionFor($account->subtype);
            if ($section !== null) {
                $sections[$section] += -$delta; // cash impact of the change
            }
        }

        $netChange = $sections[CashFlowMap::OPERATING] + $sections[CashFlowMap::INVESTING] + $sections[CashFlowMap::FINANCING];

        return [
            'operating' => $sections[CashFlowMap::OPERATING],
            'investing' => $sections[CashFlowMap::INVESTING],
            'financing' => $sections[CashFlowMap::FINANCING],
            'net_change' => $netChange,
            'cash_change' => $cashChange,
            'balanced' => $netChange === $cashChange,
        ];
    }
}
