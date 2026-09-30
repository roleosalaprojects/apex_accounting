<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Enums\VatBucket;
use App\Filament\Resources\Bills\Pages\CreateBill;
use App\Filament\Resources\Bills\Pages\ViewBill;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\TaxCode;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;

/*
 * A document's tags (department, project, fund, branch) are picked on its
 * form, land on every line of the entry it posts, and show on its page.
 */
beforeEach(function () {
    $this->company = makeCompany();
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Owner));
    Filament::setTenant($this->company);
    Repeater::fake();

    $this->sales = Department::factory()->create(['company_id' => $this->company->id, 'code' => 'SALES', 'name' => 'Sales']);
    $this->rollout = Project::factory()->create(['company_id' => $this->company->id, 'code' => 'BOTIKA-25', 'name' => 'BotikaPlus rollout']);
    $this->vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');
});

it('tags an invoice from its form, down to the entry lines and the page', function () {
    $customer = Customer::factory()->create(['company_id' => $this->company->id]);

    Livewire::test(CreateInvoice::class)
        ->assertFormFieldExists('department_id')
        ->fillForm([
            'customer_id' => $customer->id, 'invoice_date' => '2026-06-15', 'pricing_mode' => 'vat_inclusive',
            'department_id' => $this->sales->id, 'project_id' => $this->rollout->id,
            'lines' => [['description' => 'POS Terminal', 'qty' => '2', 'unit_price' => '56000', 'tax_code_id' => $this->vat12,
                'income_account_id' => account($this->company, '4200')->id]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $invoice = Invoice::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->sole();

    expect($invoice->department_id)->toBe($this->sales->id)
        ->and($invoice->project_id)->toBe($this->rollout->id)
        ->and($invoice->fund_id)->toBeNull()
        ->and($invoice->journalEntry->lines->pluck('department_id')->unique()->all())->toBe([$this->sales->id])
        ->and($invoice->journalEntry->lines->pluck('project_id')->unique()->all())->toBe([$this->rollout->id]);

    Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertOk()
        ->assertSeeInOrder(['Tags', 'SALES · Sales', 'BOTIKA-25 · BotikaPlus rollout']);
});

it('tags a bill from its form, down to the entry lines and the page', function () {
    $vendor = Vendor::factory()->create(['company_id' => $this->company->id]);

    Livewire::test(CreateBill::class)
        ->assertFormFieldExists('branch_id')
        ->fillForm([
            'vendor_id' => $vendor->id, 'bill_date' => '2026-06-05', 'pricing_mode' => 'vat_inclusive',
            'department_id' => $this->sales->id,
            'lines' => [['description' => 'Office rent', 'qty' => '1', 'unit_price' => '56000', 'tax_code_id' => $this->vat12,
                'vat_bucket' => VatBucket::Common->value, 'expense_or_asset_account_id' => account($this->company, '6100')->id]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $bill = Bill::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->sole();

    expect($bill->department_id)->toBe($this->sales->id)
        ->and($bill->journalEntry->lines->firstWhere('account_id', account($this->company, '6100')->id)->department_id)->toBe($this->sales->id)
        ->and($bill->journalEntry->lines->firstWhere('account_id', account($this->company, '2100')->id)->department_id)->toBe($this->sales->id);

    Livewire::test(ViewBill::class, ['record' => $bill->getRouteKey()])
        ->assertOk()
        ->assertSeeInOrder(['Tags', 'SALES · Sales']);
});

it('shows no Tags section on an untagged document', function () {
    $customer = Customer::factory()->create(['company_id' => $this->company->id]);

    Livewire::test(CreateInvoice::class)
        ->fillForm([
            'customer_id' => $customer->id, 'invoice_date' => '2026-06-15', 'pricing_mode' => 'vat_inclusive',
            'lines' => [['description' => 'Consulting', 'qty' => '1', 'unit_price' => '11200', 'tax_code_id' => $this->vat12,
                'income_account_id' => account($this->company, '4200')->id]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $invoice = Invoice::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->sole();

    Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertOk()
        ->assertDontSee('SALES · Sales')
        ->assertDontSee('>Tags<', false);
});
