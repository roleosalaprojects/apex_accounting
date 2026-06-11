<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Company;
use App\Services\Reports\ReportBalances;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard KPIs (§13): cash position, AR/AP totals, VAT payable estimate for
 * the active company.
 */
class LedgerStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        if (! $tenant instanceof Company) {
            return [];
        }

        $asOf = now()->toDateString();
        $perAccount = app(ReportBalances::class)->perAccount($tenant->id, null, $asOf);

        $bySubtype = [];
        $accounts = Account::query()->withoutGlobalScopes()->where('company_id', $tenant->id)->get()->keyBy('id');
        foreach ($perAccount as $row) {
            $account = $accounts->get($row['account_id']);
            if ($account === null) {
                continue;
            }
            $bySubtype[$account->subtype->value] = ($bySubtype[$account->subtype->value] ?? 0) + $row['ending'];
        }

        $peso = fn (int $minor): string => '₱'.number_format($minor / 100, 2);

        $cash = ($bySubtype['cash'] ?? 0) + ($bySubtype['bank'] ?? 0);
        $ar = $bySubtype['accounts_receivable'] ?? 0;
        $ap = -($bySubtype['accounts_payable'] ?? 0);
        $outputVat = -($bySubtype['vat_payable'] ?? 0);

        return [
            Stat::make('Cash position', $peso($cash)),
            Stat::make('Accounts receivable', $peso($ar)),
            Stat::make('Accounts payable', $peso($ap)),
            Stat::make('Output VAT (running)', $peso($outputVat)),
        ];
    }
}
