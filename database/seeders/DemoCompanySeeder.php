<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Ledger\OpenFiscalYear;
use App\Actions\Ledger\PostJournalEntry;
use App\Actions\Ledger\SetupNewCompany;
use App\Actions\Payables\PayBill;
use App\Actions\Payables\PostBill;
use App\Actions\Receivables\PostInvoice;
use App\Actions\Receivables\ReceiveCustomerPayment;
use App\Actions\Tax\AllocateCommonInputVat;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Data\Payables\BillData;
use App\Data\Payables\PayBillData;
use App\Data\Receivables\CustomerPaymentData;
use App\Data\Receivables\InvoiceData;
use App\Enums\ItemType;
use App\Enums\TaxpayerType;
use App\Enums\VatBucket;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Item;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Models\WithholdingCode;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;
use Spatie\LaravelData\DataCollection;

/**
 * Golden-master fixture (§20): Dari Ventures Corp., June 2026. Seeds the exact
 * ten transactions whose figures the suite asserts to the centavo.
 */
final class DemoCompanySeeder extends Seeder
{
    public function run(): void
    {
        $this->build();
    }

    public function build(): Company
    {
        $company = Company::factory()->create([
            'name' => 'Dari Ventures Corp.',
            'tin' => '009-123-456-00000',
            'branch_code' => '00000',
            'taxpayer_type' => TaxpayerType::Vat,
            'require_approval' => false,
        ]);

        app(SetupNewCompany::class)->handle($company);
        app(OpenFiscalYear::class)->handle($company, 2026);
        app(CompanyContext::class)->set($company->id);

        $acc = fn (string $code): int => Account::query()
            ->withoutGlobalScopes()->where('company_id', $company->id)->where('code', $code)->value('id');

        $vat12 = TaxCode::query()->where('company_id', $company->id)->where('code', 'VAT12')->value('id');
        $exempt = TaxCode::query()->where('company_id', $company->id)->where('code', 'EXEMPT')->value('id');
        $wc100 = WithholdingCode::query()->where('company_id', $company->id)->where('code', 'WC100')->value('id');

        $rice = Item::factory()->create([
            'company_id' => $company->id, 'sku' => 'RICE-25', 'name' => 'Rice 25kg', 'type' => ItemType::Inventory,
            'is_vat_exempt_item' => true, 'unit' => 'sack_25kg',
            'income_account_id' => $acc('4100'), 'cogs_account_id' => $acc('5100'), 'inventory_account_id' => $acc('1300'),
        ]);
        $pos = Item::factory()->create([
            'company_id' => $company->id, 'sku' => 'POS-T1', 'name' => 'POS Terminal', 'type' => ItemType::Inventory,
            'income_account_id' => $acc('4200'), 'cogs_account_id' => $acc('5200'), 'inventory_account_id' => $acc('1310'),
        ]);

        $riceBuyer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Rice Buyer', 'terms_days' => 30]);
        $posClient = Customer::factory()->create(['company_id' => $company->id, 'name' => 'POS Client', 'terms_days' => 30]);
        $riceTrader = Vendor::factory()->create(['company_id' => $company->id, 'name' => 'Rice Trader']);
        $posSupplier = Vendor::factory()->create(['company_id' => $company->id, 'name' => 'POS Supplier']);
        $landlord = Vendor::factory()->create(['company_id' => $company->id, 'name' => 'Landlord', 'default_withholding_code_id' => $wc100]);

        // 1 — Owners' capital.
        app(PostJournalEntry::class)->handle(new JournalEntryData(
            company_id: $company->id, entry_date: '2026-06-01', memo: 'Share capital',
            lines: new DataCollection(JournalLineData::class, [
                new JournalLineData(account_id: $acc('1120'), debit: 1_000_000_00),
                new JournalLineData(account_id: $acc('3100'), credit: 1_000_000_00),
            ]),
        ));

        // 2 — Bill B-1: rice (exempt) into inventory.
        app(PostBill::class)->handle(BillData::from([
            'company_id' => $company->id, 'vendor_id' => $riceTrader->id, 'bill_date' => '2026-06-02',
            'lines' => [['description' => 'Rice 25kg', 'qty' => '1000', 'unit_price' => 2_000_00, 'tax_code_id' => $exempt,
                'item_id' => $rice->id, 'expense_or_asset_account_id' => $acc('1300')]],
        ]));

        // 3 — Bill B-2: POS (direct_vatable).
        app(PostBill::class)->handle(BillData::from([
            'company_id' => $company->id, 'vendor_id' => $posSupplier->id, 'bill_date' => '2026-06-03',
            'lines' => [['description' => 'POS units', 'qty' => '10', 'unit_price' => 20_000_00, 'tax_code_id' => $vat12,
                'vat_bucket' => VatBucket::DirectVatable->value, 'item_id' => $pos->id, 'expense_or_asset_account_id' => $acc('1310')]],
        ]));

        // 4 — Bill B-3: rent (common, inclusive).
        app(PostBill::class)->handle(BillData::from([
            'company_id' => $company->id, 'vendor_id' => $landlord->id, 'bill_date' => '2026-06-05', 'pricing_mode' => 'vat_inclusive',
            'lines' => [['description' => 'Office rent', 'qty' => '1', 'unit_price' => 56_000_00, 'tax_code_id' => $vat12,
                'vat_bucket' => VatBucket::Common->value, 'expense_or_asset_account_id' => $acc('6100')]],
        ]));

        // 5 — INV-1: rice sale (exempt).
        $inv1 = app(PostInvoice::class)->handle(InvoiceData::from([
            'company_id' => $company->id, 'customer_id' => $riceBuyer->id, 'invoice_date' => '2026-06-10',
            'lines' => [['description' => 'Rice 25kg', 'qty' => '360', 'unit_price' => 2_500_00, 'tax_code_id' => $exempt,
                'item_id' => $rice->id, 'income_account_id' => $acc('4100')]],
        ]));

        // 6 — INV-2: POS sale (vatable inclusive).
        $inv2 = app(PostInvoice::class)->handle(InvoiceData::from([
            'company_id' => $company->id, 'customer_id' => $posClient->id, 'invoice_date' => '2026-06-12',
            'lines' => [['description' => 'POS Terminal', 'qty' => '2', 'unit_price' => 56_000_00, 'tax_code_id' => $vat12,
                'item_id' => $pos->id, 'income_account_id' => $acc('4200')]],
        ]));

        // 7 — PMT-1: Rice Buyer pays INV-1.
        app(ReceiveCustomerPayment::class)->handle(CustomerPaymentData::from([
            'company_id' => $company->id, 'customer_id' => $riceBuyer->id, 'payment_date' => '2026-06-15',
            'deposit_to_account_id' => $acc('1120'), 'amount' => 900_000_00,
            'applications' => [['invoice_id' => $inv1->id, 'amount' => 900_000_00]],
        ]));

        // 8 — PMT-2: POS Client pays INV-2.
        app(ReceiveCustomerPayment::class)->handle(CustomerPaymentData::from([
            'company_id' => $company->id, 'customer_id' => $posClient->id, 'payment_date' => '2026-06-16',
            'deposit_to_account_id' => $acc('1120'), 'amount' => 112_000_00,
            'applications' => [['invoice_id' => $inv2->id, 'amount' => 112_000_00]],
        ]));

        // 9 — VP-1: pay rent with 5% EWT.
        $b3 = Bill::query()->withoutGlobalScopes()->where('company_id', $company->id)->where('vendor_id', $landlord->id)->firstOrFail();
        app(PayBill::class)->handle(PayBillData::from([
            'company_id' => $company->id, 'vendor_id' => $landlord->id, 'payment_date' => '2026-06-20',
            'paid_from_account_id' => $acc('1120'),
            'applications' => [['bill_id' => $b3->id, 'amount' => 56_000_00]],
        ]));

        // 10 — Quarterly common input VAT allocation (June as the demo quarter -> Q2).
        app(AllocateCommonInputVat::class)->handle($company->fresh(), 2026, 2);

        return $company;
    }
}
