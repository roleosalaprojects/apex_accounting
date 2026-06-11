<?php

declare(strict_types=1);

use App\Models\Account;
use App\Services\Reports\ApAgingReport;
use App\Services\Reports\BalanceSheetReport;
use App\Services\Reports\CashDisbursementsBook;
use App\Services\Reports\CashFlowReport;
use App\Services\Reports\CashReceiptsBook;
use App\Services\Reports\EwtSummaryReport;
use App\Services\Reports\ProfitAndLossReport;
use App\Services\Reports\PurchaseBook;
use App\Services\Reports\ReportBalances;
use App\Services\Reports\SalesBook;
use App\Services\Reports\TrialBalanceReport;
use App\Services\Reports\VatSummaryReport;
use Database\Seeders\DemoCompanySeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->company = (new DemoCompanySeeder)->build();
    // Map of account code => signed ending balance as of quarter end.
    $perAccount = app(ReportBalances::class)->perAccount($this->company->id, null, '2026-06-30');
    $accounts = Account::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->get()->keyBy('id');
    $this->ending = [];
    foreach ($perAccount as $row) {
        $this->ending[$accounts->get($row['account_id'])->code] = $row['ending'];
    }
});

function endingOf(string $code): int
{
    return test()->ending[$code] ?? 0;
}

it('ledger:verify passes on the golden master', function () {
    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('matches the §20 account balances exactly', function () {
    expect(endingOf('1120'))->toBe(1_958_500_00)        // Cash in Bank
        ->and(endingOf('1200'))->toBe(0)                 // AR
        ->and(endingOf('2100'))->toBe(-2_224_000_00)     // AP (credit)
        ->and(endingOf('1300'))->toBe(1_280_000_00)      // Inventory — Rice
        ->and(endingOf('1310'))->toBe(160_000_00)        // Inventory — POS
        ->and(endingOf('1400'))->toBe(24_600_00)         // Input VAT
        ->and(endingOf('1410'))->toBe(0)                 // Deferred Common Input VAT
        ->and(endingOf('2200'))->toBe(-12_000_00)        // Output VAT (credit)
        ->and(endingOf('2210'))->toBe(-2_500_00);        // EWT Payable (credit)
});

it('produces a balanced trial balance', function () {
    $tb = app(TrialBalanceReport::class)->build($this->company->id, '2026-06-30');
    expect($tb['balanced'])->toBeTrue()
        ->and($tb['total_debit'])->toBe($tb['total_credit']);
});

it('computes net income of ₱184,600', function () {
    $pnl = app(ProfitAndLossReport::class)->build($this->company->id, '2026-01-01', '2026-06-30');
    expect($pnl['total_income'])->toBe(1_000_000_00)
        ->and($pnl['net_income'])->toBe(184_600_00);
});

it('produces a balanced balance sheet that ties to §20', function () {
    $bs = app(BalanceSheetReport::class)->build($this->company->fresh(), '2026-06-30');
    expect($bs['balanced'])->toBeTrue()
        ->and($bs['total_assets'])->toBe(3_423_100_00)
        ->and($bs['total_liabilities'])->toBe(2_238_500_00)
        ->and($bs['total_equity'])->toBe(1_184_600_00)
        ->and($bs['current_year_earnings'])->toBe(184_600_00);
});

it('produces a cash flow that ties to actual cash movement', function () {
    $cf = app(CashFlowReport::class)->build($this->company->id, '2026-01-01', '2026-06-30');
    expect($cf['balanced'])->toBeTrue()
        ->and($cf['net_change'])->toBe(1_958_500_00); // cash built from zero
});

it('produces the §20 sales book totals', function () {
    $sb = app(SalesBook::class)->build($this->company->id, '2026-04-01', '2026-06-30')['totals'];
    expect($sb['exempt'])->toBe(900_000_00)
        ->and($sb['vatable'])->toBe(100_000_00)
        ->and($sb['output_vat'])->toBe(12_000_00)
        ->and($sb['total'])->toBe(1_012_000_00);
});

it('splits input VAT by bucket in the purchase book', function () {
    $pb = app(PurchaseBook::class)->build($this->company->id, '2026-04-01', '2026-06-30')['totals'];
    expect($pb['input_vat_direct'])->toBe(24_000_00)
        ->and($pb['input_vat_common'])->toBe(6_000_00)
        ->and($pb['exempt'])->toBe(2_000_000_00);
});

it('produces the 2550Q working paper with ₱12,600 excess input carryover', function () {
    $vat = app(VatSummaryReport::class)->build($this->company->id, 2026, 2, '2026-04-01', '2026-06-30');
    expect($vat['exempt_sales'])->toBe(900_000_00)
        ->and($vat['vatable_sales'])->toBe(100_000_00)
        ->and($vat['output_vat'])->toBe(12_000_00)
        ->and($vat['creditable_input_vat'])->toBe(24_600_00)
        ->and($vat['vat_payable'])->toBe(0)
        ->and($vat['carryover'])->toBe(12_600_00)
        ->and($vat['allocation_id'])->not->toBeNull();
});

it('summarizes EWT for 2307 generation', function () {
    $ewt = app(EwtSummaryReport::class)->build($this->company->id, '2026-06-01', '2026-06-30');
    expect($ewt['total_ewt'])->toBe(2_500_00)
        ->and($ewt['rows'][0]['atc'])->toBe('WC100')
        ->and($ewt['rows'][0]['base'])->toBe(50_000_00);
});

it('reconciles CRB and CDB to cash movement', function () {
    $crb = app(CashReceiptsBook::class)->build($this->company->id, '2026-06-01', '2026-06-30');
    $cdb = app(CashDisbursementsBook::class)->build($this->company->id, '2026-06-01', '2026-06-30');

    expect($crb['total'])->toBe(1_012_000_00)
        ->and($cdb['total'])->toBe(53_500_00)
        ->and($crb['total'] - $cdb['total'])->toBe(958_500_00);
});

it('ages AP — unpaid B-1 and B-2 outstanding', function () {
    $aging = app(ApAgingReport::class)->build($this->company->id, '2026-06-30');
    expect($aging['total'])->toBe(2_224_000_00);
});
