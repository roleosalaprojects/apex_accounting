<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Banking\CompleteReconciliation;
use App\Actions\Banking\StartReconciliation;
use App\Actions\Payables\PostBill;
use App\Actions\Receivables\PostInvoice;
use App\Data\Payables\BillData;
use App\Data\Receivables\InvoiceData;
use App\Enums\CompanyRole;
use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\Bill;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Banking\BankBalanceService;
use App\Services\Fx\ExchangeRateService;
use App\Support\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Demo data for the areas the three-year history leaves empty: USD exchange
 * rates and two open foreign-currency documents for FX revaluation, and two
 * months of bank statement lines with last month reconciled for Banking.
 * Safe to run again: each part skips itself once its data exists.
 */
final class DemoOperationsSeeder extends Seeder
{
    private Company $company;

    private ?User $actor = null;

    private CarbonImmutable $today;

    public function run(): void
    {
        $this->company = Company::query()->withoutGlobalScopes()->where('name', 'Dari Ventures Corp.')->first()
            ?? throw new RuntimeException('Run DemoCompanySeeder first.');
        app(CompanyContext::class)->set($this->company->id);
        $this->actor = $this->company->users()->wherePivot('role', CompanyRole::Owner->value)->first();
        $this->today = CarbonImmutable::today();

        DB::transaction(function (): void {
            $this->exchangeRates();
            $this->foreignDocuments();
            $this->bankStatement();
        });
    }

    /** Month-start USD rates from the company's first month to today. */
    private function exchangeRates(): void
    {
        if (ExchangeRate::query()->where('currency_code', 'USD')->exists()) {
            return;
        }

        $month = CarbonImmutable::parse(HistoricalDataSeeder::START)->startOfMonth();
        for ($i = 0; $month->lessThanOrEqualTo($this->today); $i++, $month = $month->addMonth()) {
            ExchangeRate::query()->create([
                'company_id' => $this->company->id,
                'currency_code' => 'USD',
                'rate_date' => $month->toDateString(),
                'rate' => round(56.30 + 0.9 * sin($i / 2), 2),
            ]);
        }
    }

    /** An unpaid USD bill and an uncollected USD invoice, so the FX report has something to revalue. */
    private function foreignDocuments(): void
    {
        if (Bill::query()->where('currency_code', 'USD')->exists()) {
            return;
        }

        $rates = app(ExchangeRateService::class);
        $zeroRated = (int) TaxCode::query()->where('code', 'ZERO')->value('id');

        $billDate = $this->today->subDays(45);
        $billRate = $rates->rateFor($this->company->id, 'USD', $billDate->toDateString());
        $supplier = Vendor::factory()->create([
            'company_id' => $this->company->id, 'name' => 'Shenzhen POS Devices Co., Ltd.', 'tin' => null,
            'is_vat_registered' => false, 'terms_days' => 60,
        ]);
        $bill = app(PostBill::class)->handle(BillData::from([
            'company_id' => $this->company->id, 'vendor_id' => $supplier->id, 'bill_date' => $billDate->toDateString(),
            'lines' => [['description' => 'Annual POS software licences (USD 12,000)', 'qty' => '1',
                'unit_price' => (int) round(12_000_00 * $billRate), 'tax_code_id' => $zeroRated,
                'expense_or_asset_account_id' => $this->account('6500')->id]],
        ]), $this->actor);
        $bill->forceFill(['currency_code' => 'USD', 'exchange_rate' => $billRate, 'foreign_total' => 12_000_00])->save();

        $invoiceDate = $this->today->subDays(30);
        $invoiceRate = $rates->rateFor($this->company->id, 'USD', $invoiceDate->toDateString());
        $client = Customer::factory()->create([
            'company_id' => $this->company->id, 'name' => 'Singapore Grocers Pte Ltd', 'tin' => null, 'terms_days' => 45,
        ]);
        $invoice = app(PostInvoice::class)->handle(InvoiceData::from([
            'company_id' => $this->company->id, 'customer_id' => $client->id, 'invoice_date' => $invoiceDate->toDateString(),
            'pricing_mode' => 'vat_exclusive',
            'lines' => [['description' => 'POS integration services — Singapore rollout (USD 8,500)', 'qty' => '1',
                'unit_price' => (int) round(8_500_00 * $invoiceRate), 'tax_code_id' => $zeroRated,
                'income_account_id' => $this->account('4300')->id]],
        ]), $this->actor);
        $invoice->forceFill(['currency_code' => 'USD', 'exchange_rate' => $invoiceRate, 'foreign_total' => 8_500_00])->save();
    }

    /**
     * The BDO statement for last month and this month to date: every ledger
     * movement on the bank account appears as a matched line, last month is
     * reconciled, and this month has two bank-only lines still to deal with.
     */
    private function bankStatement(): void
    {
        $bank = BankAccount::query()->where('account_id', $this->account('1120')->id)->first();
        if ($bank === null || BankStatementLine::query()->where('bank_account_id', $bank->id)->exists()) {
            return;
        }

        $lastMonth = $this->today->subMonthNoOverflow()->startOfMonth();
        $cutoff = $this->today->subDays(2); // the last two days' postings have not reached the statement yet
        $balance = app(BankBalanceService::class)->currentBalance($bank, $lastMonth->subDay()->toDateString());

        $movements = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $this->company->id)
            ->where('journal_lines.account_id', $bank->account_id)
            ->whereIn('journal_entries.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
            ->whereDate('journal_entries.entry_date', '>=', $lastMonth->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $cutoff->toDateString())
            ->orderBy('journal_entries.entry_date')->orderBy('journal_lines.id')
            ->get(['journal_entries.id', 'journal_entries.number', 'journal_entries.memo', 'journal_entries.entry_date', 'journal_lines.debit', 'journal_lines.credit']);

        $lines = [];
        foreach ($movements as $movement) {
            $lines[] = [
                'date' => CarbonImmutable::parse($movement->entry_date)->toDateString(),
                'description' => (string) $movement->memo,
                'reference' => (string) $movement->number,
                'amount' => (int) $movement->debit - (int) $movement->credit,
                'journal_entry_id' => (int) $movement->id,
            ];
        }
        $lines[] = ['date' => $this->today->subDays(6)->toDateString(), 'description' => 'INSTAPAY CREDIT — unidentified sender', 'reference' => 'IP'.$this->today->format('ymd').'0417', 'amount' => 25_000_00, 'journal_entry_id' => null];
        $lines[] = ['date' => $this->today->subDays(3)->toDateString(), 'description' => 'Interest credit', 'reference' => null, 'amount' => 312_50, 'journal_entry_id' => null];
        usort($lines, fn (array $a, array $b): int => [$a['date'], $a['reference'] ?? ''] <=> [$b['date'], $b['reference'] ?? '']);

        foreach ($lines as $line) {
            $balance += $line['amount'];
            BankStatementLine::query()->create([
                'company_id' => $this->company->id,
                'bank_account_id' => $bank->id,
                'txn_date' => $line['date'],
                'description' => $line['description'],
                'reference' => $line['reference'],
                'amount' => $line['amount'],
                'balance' => $balance,
                'status' => $line['journal_entry_id'] === null ? 'unmatched' : 'matched',
                'journal_entry_id' => $line['journal_entry_id'],
                'source' => 'csv',
                'import_ref' => 'BDO-'.$this->today->format('Ym'),
            ]);
        }

        // Last month reconciled: everything on the books had cleared the bank.
        $statementDate = $lastMonth->endOfMonth()->toDateString();
        $reconciliation = app(StartReconciliation::class)->handle(
            $bank, $statementDate, app(BankBalanceService::class)->currentBalance($bank, $statementDate), $this->actor,
        );
        app(CompleteReconciliation::class)->handle($reconciliation);
    }

    private function account(string $code): Account
    {
        return Account::query()->where('code', $code)->firstOrFail();
    }
}
