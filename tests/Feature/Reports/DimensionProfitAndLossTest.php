<?php

declare(strict_types=1);

use App\Actions\Payables\PostBill;
use App\Actions\Receivables\PostInvoice;
use App\Data\Payables\BillData;
use App\Data\Receivables\InvoiceData;
use App\Enums\CompanyRole;
use App\Enums\ItemType;
use App\Filament\Pages\Reports\DimensionProfitAndLoss;
use App\Filament\Resources\JournalEntries\Pages\ViewJournalEntry;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Item;
use App\Models\JournalLine;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Services\Reports\DimensionProfitAndLossReport;
use App\Services\Reports\ProfitAndLossReport;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
    $this->sales = Department::factory()->create(['company_id' => $this->company->id, 'code' => 'SALES', 'name' => 'Sales']);
    $this->operations = Department::factory()->create(['company_id' => $this->company->id, 'code' => 'OPS', 'name' => 'Operations']);
    $this->vat12 = TaxCode::query()->where('code', 'VAT12')->value('id');
});

it('splits profit and loss by department, with untagged activity on its own row', function () {
    app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id,
        'customer_id' => Customer::factory()->create(['company_id' => $this->company->id])->id,
        'invoice_date' => '2026-06-10',
        'department_id' => $this->sales->id,
        'lines' => [['description' => 'Consulting', 'qty' => '1', 'unit_price' => 112_000_00, 'tax_code_id' => $this->vat12,
            'income_account_id' => account($this->company, '4300')->id]],
    ]));
    postEntry($this->company, '2026-06-15', [
        ['account_id' => account($this->company, '6400')->id, 'debit' => 20_000_00, 'department_id' => $this->operations->id],
        ['account_id' => account($this->company, '6200')->id, 'debit' => 5_000_00],
        ['account_id' => account($this->company, '1110')->id, 'credit' => 25_000_00],
    ]);

    $report = app(DimensionProfitAndLossReport::class)->build($this->company->id, 'department', '2026-06-01', '2026-06-30');
    $rows = collect($report['rows'])->keyBy('name');
    $pnl = app(ProfitAndLossReport::class)->build($this->company->id, '2026-06-01', '2026-06-30');

    expect($rows['Sales']['income'])->toBe(100_000_00) // VAT-inclusive ₱112,000 → ₱100,000 net
        ->and($rows['Operations']['expenses'])->toBe(20_000_00)
        ->and($rows['Untagged']['expenses'])->toBe(5_000_00)
        ->and($report['total_income'])->toBe($pnl['total_income'])
        ->and($report['net_income'])->toBe($pnl['net_income']);
});

it("carries an invoice's tags onto its cost of sales", function () {
    $item = Item::factory()->create([
        'company_id' => $this->company->id, 'sku' => 'WIDGET', 'type' => ItemType::Inventory,
        'income_account_id' => account($this->company, '4200')->id,
        'cogs_account_id' => account($this->company, '5200')->id,
        'inventory_account_id' => account($this->company, '1310')->id,
    ]);
    app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id,
        'vendor_id' => Vendor::factory()->create(['company_id' => $this->company->id])->id,
        'bill_date' => '2026-06-02',
        'lines' => [['description' => 'Widgets', 'qty' => '10', 'unit_price' => 1_000_00, 'tax_code_id' => $this->vat12,
            'vat_bucket' => 'direct_vatable', 'item_id' => $item->id, 'expense_or_asset_account_id' => account($this->company, '1310')->id]],
    ]));

    app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id,
        'customer_id' => Customer::factory()->create(['company_id' => $this->company->id])->id,
        'invoice_date' => '2026-06-10',
        'department_id' => $this->sales->id,
        'lines' => [['description' => 'Widget', 'qty' => '2', 'unit_price' => 2_240_00, 'tax_code_id' => $this->vat12,
            'income_account_id' => account($this->company, '4200')->id, 'item_id' => $item->id]],
    ]));

    $cogs = JournalLine::query()->where('account_id', account($this->company, '5200')->id)->sole();

    expect($cogs->department_id)->toBe($this->sales->id)
        ->and($cogs->debit->minor)->toBe(2_000_00);
});

it('rejects an unknown dimension', function () {
    expect(fn () => app(DimensionProfitAndLossReport::class)->build($this->company->id, 'colour', '2026-06-01', '2026-06-30'))
        ->toThrow(InvalidArgumentException::class);
});

it('shows the report and the tags on journal lines', function () {
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Owner));
    Filament::setTenant($this->company);
    $entry = postEntry($this->company, '2026-06-15', [
        ['account_id' => account($this->company, '6400')->id, 'debit' => 1_000_00, 'department_id' => $this->operations->id],
        ['account_id' => account($this->company, '1110')->id, 'credit' => 1_000_00],
    ]);

    Livewire::test(ViewJournalEntry::class, ['record' => $entry->getKey()])->assertSee('Operations');

    Livewire::test(DimensionProfitAndLoss::class)
        ->set('from', '2026-06-01')
        ->set('asOf', '2026-06-30')
        ->assertSee('Operations')
        ->set('entity', 'branch')
        ->assertOk()
        ->assertSee('Untagged');
});
