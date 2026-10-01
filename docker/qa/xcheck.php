<?php
// Financial cross-checks on the demo company as of today.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
app(App\Support\CompanyContext::class)->set(1);
$company = App\Models\Company::query()->findOrFail(1);
$asOf = '2026-10-01';
$peso = fn (int $m): string => number_format($m / 100, 2);
$check = fn (string $what, int $a, int $b) => printf("%-46s %16s vs %16s  %s\n", $what, $peso($a), $peso($b), $a === $b ? 'OK' : 'MISMATCH');
$balance = function (string $code) use ($asOf): int {
    $id = DB::table('accounts')->where('company_id', 1)->where('code', $code)->value('id');
    return (int) DB::table('journal_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
        ->where('journal_entries.company_id', 1)->whereIn('journal_entries.status', ['posted', 'reversed'])->whereDate('journal_entries.entry_date', '<=', $asOf)
        ->where('journal_lines.account_id', $id)->selectRaw('COALESCE(SUM(debit),0)-COALESCE(SUM(credit),0) b')->value('b');
};
$tb = app(App\Services\Reports\TrialBalanceReport::class)->build(1, $asOf);
printf("%-46s %s\n", 'Trial balance balanced', ($tb['balanced'] ?? false) ? 'OK' : 'NOT BALANCED');
$bs = app(App\Services\Reports\BalanceSheetReport::class)->build($company, $asOf);
$assets = (int) ($bs['total_assets'] ?? 0); $le = (int) (($bs['total_liabilities'] ?? 0) + ($bs['total_equity'] ?? 0));
$check('Balance sheet: assets = liabilities + equity', $assets, $le);
$ar = app(App\Services\Reports\ArAgingReport::class)->build(1, $asOf);
$check('AR aging total = 1200 ledger', (int) $ar['total'], $balance('1200'));
$ap = app(App\Services\Reports\ApAgingReport::class)->build(1, $asOf);
$check('AP aging total = 2100 ledger', (int) $ap['total'], -$balance('2100'));
$stock = app(App\Services\Reports\StockSummaryReport::class)->build(1, '2000-01-01', $asOf);
$check('Stock summary closing = inventory accounts', (int) $stock['totals']['closing_value'], (int) $stock['ledger_value']);
$fa = app(App\Services\Reports\FixedAssetRegister::class)->build(1, '2026-01-01', $asOf);
$check('FA register cost = ledger', (int) $fa['totals']['cost'], (int) $fa['ledger']['cost']);
$check('FA register accumulated = ledger', (int) $fa['totals']['accumulated_end'], (int) $fa['ledger']['accumulated']);
$ewt = app(App\Services\Reports\EwtSummaryReport::class)->build(1, '2026-01-01', $asOf);
$check('EWT summary = withholding_transactions', (int) $ewt['total_ewt'], (int) DB::table('withholding_transactions')->where('company_id', 1)->whereBetween('transaction_date', ['2026-01-01', $asOf])->sum('ewt'));
$pl = app(App\Services\Reports\ProfitAndLossReport::class)->build(1, '2026-01-01', $asOf);
printf("%-46s %s net income YTD\n", 'P&L builds', $peso((int) ($pl['net_income'] ?? 0)));
$cf = app(App\Services\Reports\CashFlowReport::class)->build(1, '2026-01-01', $asOf);
printf("%-46s %s\n", 'Cash flow builds', isset($cf['closing_cash']) ? 'closing cash '.$peso((int) $cf['closing_cash']) : 'keys: '.implode(',', array_keys($cf)));
