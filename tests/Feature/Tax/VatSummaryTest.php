<?php

declare(strict_types=1);

use App\Actions\Integration\ImportPosZReading;
use App\Actions\Ledger\PostDraftJournalEntry;
use App\Actions\Ledger\ReverseJournalEntry;
use App\Actions\Payables\PostBill;
use App\Actions\Receivables\PostCreditMemo;
use App\Actions\Receivables\PostInvoice;
use App\Data\Payables\BillData;
use App\Data\Receivables\CreditMemoData;
use App\Data\Receivables\InvoiceData;
use App\Enums\CompanyRole;
use App\Enums\VatBucket;
use App\Models\Customer;
use App\Models\PosZReading;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Services\Reports\SalesBook;
use App\Services\Reports\VatSummaryReport;

/*
 * The 2550Q working paper comes from the quarter's documents (the sales and
 * purchase books plus the saved common-VAT allocation), never from movement
 * on the VAT accounts, which also carries last quarter's remittance.
 */
beforeEach(function () {
    $this->company = makeCompany();
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $this->vendor = Vendor::factory()->create(['company_id' => $this->company->id]);
    $this->vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');

    // Q2 2026: one VATable sale of ₱100,000 + ₱12,000 VAT, one purchase of
    // ₱200,000 + ₱24,000 directly attributable input VAT.
    app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id, 'customer_id' => $this->customer->id, 'invoice_date' => '2026-05-10',
        'lines' => [['description' => 'POS terminals', 'qty' => '1', 'unit_price' => 112_000_00,
            'tax_code_id' => $this->vat12, 'income_account_id' => account($this->company, '4200')->id]],
    ]));
    app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'bill_date' => '2026-05-15',
        'lines' => [['description' => 'Terminals', 'qty' => '10', 'unit_price' => 20_000_00, 'tax_code_id' => $this->vat12,
            'vat_bucket' => VatBucket::DirectVatable->value, 'expense_or_asset_account_id' => account($this->company, '1310')->id]],
    ]));
});

function q2Vat(): array
{
    return app(VatSummaryReport::class)->build(test()->company->id, 2026, 2, '2026-04-01', '2026-06-30');
}

it('reports the quarter from its documents, ignoring last quarter\'s remittance', function () {
    expect(q2Vat())->toMatchArray(['output_vat' => 12_000_00, 'creditable_input_vat' => 24_000_00, 'vat_payable' => 0, 'carryover' => 12_000_00]);

    // Q1's VAT is remitted on April 25: Dr Output VAT, Cr Input VAT, Cr bank.
    postEntry($this->company, '2026-04-25', [
        ['account_id' => account($this->company, '2200')->id, 'debit' => 5_000_00],
        ['account_id' => account($this->company, '1400')->id, 'credit' => 3_000_00],
        ['account_id' => account($this->company, '1120')->id, 'credit' => 2_000_00],
    ]);

    expect(q2Vat())->toMatchArray(['output_vat' => 12_000_00, 'creditable_input_vat' => 24_000_00, 'vat_payable' => 0, 'carryover' => 12_000_00]);
});

it('counts posted POS sales and sales returns in the sales book and the return', function () {
    $reading = PosZReading::factory()->create([
        'company_id' => $this->company->id, 'business_date' => '2026-06-20', 'reference' => 'Z-0620',
        'vatable_sales' => 50_000_00, 'vat_amount' => 6_000_00, 'exempt_sales' => 10_000_00, 'zero_rated_sales' => 0, 'discounts' => 0,
        'tenders' => ['cash' => 66_000_00],
    ]);
    $draft = app(ImportPosZReading::class)->handle($reading, $this->owner);

    // Imported but not yet approved: not in the ledger, so not in the book.
    expect(app(SalesBook::class)->build($this->company->id, '2026-04-01', '2026-06-30')['totals']['output_vat'])->toBe(12_000_00);

    $posted = app(PostDraftJournalEntry::class)->handle($draft, $this->owner);

    $memo = app(PostCreditMemo::class)->handle(CreditMemoData::from([
        'company_id' => $this->company->id, 'customer_id' => $this->customer->id, 'memo_date' => '2026-06-25',
        'lines' => [['description' => 'Returned terminal', 'qty' => '1', 'unit_price' => 11_200_00,
            'tax_code_id' => $this->vat12, 'income_account_id' => account($this->company, '4200')->id]],
    ]));

    $book = app(SalesBook::class)->build($this->company->id, '2026-04-01', '2026-06-30');

    expect($book['totals'])->toMatchArray([
        'vatable' => 100_000_00 + 50_000_00 - 10_000_00,
        'output_vat' => 12_000_00 + 6_000_00 - 1_200_00,
        'exempt' => 10_000_00,
    ]);
    expect(collect($book['rows'])->pluck('number')->all())->toContain('Z-0620', $memo->number)
        ->and(collect($book['rows'])->firstWhere('number', $memo->number)['output_vat'])->toBe(-1_200_00);
    expect(q2Vat())->toMatchArray(['exempt_sales' => 10_000_00, 'vatable_sales' => 140_000_00, 'output_vat' => 16_800_00]);

    // A reversed POS entry leaves the book again.
    app(ReverseJournalEntry::class)->handle($posted, 'Duplicate reading', null, $this->owner);

    expect(app(SalesBook::class)->build($this->company->id, '2026-04-01', '2026-06-30')['totals']['output_vat'])->toBe(12_000_00 - 1_200_00);
});
