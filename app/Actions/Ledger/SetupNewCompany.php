<?php

declare(strict_types=1);

namespace App\Actions\Ledger;

use App\Enums\AccountSubtype;
use App\Models\Account;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\TaxCode;
use App\Models\WithholdingCode;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the default PH SME chart of accounts (§4.1) and document sequences for
 * a freshly-created company. Idempotent — skips codes/keys already present.
 */
final class SetupNewCompany
{
    /**
     * code => [name, subtype, is_system]
     *
     * @return array<string, array{0: string, 1: AccountSubtype, 2: bool}>
     */
    public const ACCOUNTS = [
        // Assets
        '1110' => ['Cash on Hand', AccountSubtype::Cash, false],
        '1120' => ['Cash in Bank', AccountSubtype::Bank, false],
        '1200' => ['Accounts Receivable', AccountSubtype::AccountsReceivable, true],
        '1300' => ['Inventory — Rice', AccountSubtype::Inventory, true],
        '1310' => ['Inventory — POS Hardware', AccountSubtype::Inventory, true],
        '1400' => ['Input VAT', AccountSubtype::OtherCurrentAsset, true],
        '1410' => ['Deferred Input VAT — Common', AccountSubtype::OtherCurrentAsset, true],
        '1450' => ['Creditable Withholding Tax', AccountSubtype::OtherCurrentAsset, true],
        '1500' => ['Fixed Assets', AccountSubtype::FixedAsset, false],
        '1510' => ['Accumulated Depreciation', AccountSubtype::AccumulatedDepreciation, true],
        // Liabilities
        '2100' => ['Accounts Payable', AccountSubtype::AccountsPayable, true],
        '2200' => ['Output VAT', AccountSubtype::VatPayable, true],
        '2210' => ['EWT Payable', AccountSubtype::WithholdingPayable, true],
        '2220' => ['Withholding Tax Payable — Compensation', AccountSubtype::WithholdingPayable, true],
        '2230' => ['Statutory Payables', AccountSubtype::OtherCurrentLiability, true],
        // Equity
        '3100' => ['Share Capital', AccountSubtype::Equity, false],
        '3900' => ['Retained Earnings', AccountSubtype::RetainedEarnings, true],
        '3950' => ['Opening Balance Equity', AccountSubtype::Equity, true],
        // Income
        '4100' => ['Sales — Rice (VAT-Exempt)', AccountSubtype::Income, false],
        '4200' => ['Sales — POS Systems (VATable)', AccountSubtype::Income, false],
        '4300' => ['Service Income — POS (VATable)', AccountSubtype::Income, false],
        '4900' => ['Gain/Loss on Asset Disposal', AccountSubtype::OtherIncome, false],
        '4950' => ['Foreign Exchange Gain (Loss)', AccountSubtype::OtherIncome, false],
        // COGS
        '5100' => ['COGS — Rice', AccountSubtype::Cogs, true],
        '5200' => ['COGS — POS Hardware', AccountSubtype::Cogs, true],
        // Expenses
        '6100' => ['Rent Expense', AccountSubtype::Expense, false],
        '6200' => ['Utilities Expense', AccountSubtype::Expense, false],
        '6300' => ['Salaries & Wages', AccountSubtype::Expense, false],
        '6310' => ['Employer Contributions Expense', AccountSubtype::Expense, false],
        '6400' => ['Office Supplies', AccountSubtype::Expense, false],
        '6500' => ['Professional Fees', AccountSubtype::Expense, false],
        '6600' => ['Transportation & Delivery', AccountSubtype::Expense, false],
        '6800' => ['Depreciation Expense', AccountSubtype::DepreciationExpense, false],
        '6850' => ['Non-creditable Input VAT', AccountSubtype::Expense, false],
        '6999' => ['Rounding Differences', AccountSubtype::OtherExpense, true],
    ];

    /**
     * key => [prefix, padding]
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public const SEQUENCES = [
        'journal_entry' => ['JE', 6],
        'invoice' => ['INV', 6],
        'bill' => ['BILL', 6],
        'payment_in' => ['PMT', 6],
        'payment_out' => ['VP', 6],
        'credit_memo' => ['CM', 6],
        'debit_memo' => ['DM', 6],
        'asset' => ['FA', 6],
        'recurring_run' => ['RUN', 6],
        'collection_receipt' => ['CR', 6],
        'payment_voucher' => ['PV', 6],
    ];

    /**
     * code => [name, rate_bp, kind]
     *
     * @return array<string, array{0: string, 1: int, 2: string}>
     */
    public const TAX_CODES = [
        'VAT12' => ['VAT 12%', 1200, 'output'],
        'EXEMPT' => ['VAT-Exempt', 0, 'output'],
        'ZERO' => ['Zero-Rated', 0, 'output'],
    ];

    /**
     * code => [name, rate_bp, atc, applies_to]
     *
     * @return array<string, array{0: string, 1: int, 2: string, 3: string}>
     */
    public const WITHHOLDING_CODES = [
        'WC158' => ['EWT — Goods 1%', 100, 'WC158', 'purchase'],
        'WC160' => ['EWT — Services 2%', 200, 'WC160', 'purchase'],
        'WC100' => ['EWT — Rental 5%', 500, 'WC100', 'purchase'],
        'WI010' => ['EWT — Professional 10%', 1000, 'WI010', 'purchase'],
    ];

    public function handle(Company $company): Company
    {
        DB::transaction(function () use ($company): void {
            foreach (self::TAX_CODES as $code => [$name, $rateBp, $kind]) {
                TaxCode::query()->firstOrCreate(
                    ['company_id' => $company->id, 'code' => $code],
                    ['name' => $name, 'rate_bp' => $rateBp, 'kind' => $kind],
                );
            }

            foreach (self::WITHHOLDING_CODES as $code => [$name, $rateBp, $atc, $appliesTo]) {
                WithholdingCode::query()->firstOrCreate(
                    ['company_id' => $company->id, 'code' => $code],
                    ['name' => $name, 'rate_bp' => $rateBp, 'atc' => $atc, 'applies_to' => $appliesTo],
                );
            }

            foreach (self::ACCOUNTS as $code => [$name, $subtype, $isSystem]) {
                Account::query()->firstOrCreate(
                    ['company_id' => $company->id, 'code' => $code],
                    [
                        'name' => $name,
                        'type' => $subtype->type(),
                        'subtype' => $subtype,
                        'normal_balance' => $subtype->normalBalance(),
                        'is_system' => $isSystem,
                        'is_active' => true,
                    ],
                );
            }

            foreach (self::SEQUENCES as $key => [$prefix, $padding]) {
                DocumentSequence::query()->firstOrCreate(
                    ['company_id' => $company->id, 'key' => $key],
                    ['prefix' => $prefix, 'padding' => $padding, 'next_number' => 1],
                );
            }
        });

        return $company;
    }
}
