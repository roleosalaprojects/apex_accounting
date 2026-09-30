<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Enums\ItemType;
use App\Filament\Pages\OpeningBalances;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\Vendor;
use App\Services\Inventory\InventoryService;
use App\Services\Onboarding\OpeningBalanceSetup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Cutting over from the old books in one guided pass: the trial balance,
 * the customers' open invoices, the vendors' open bills and the stock on
 * hand, every piece offset to Opening Balance Equity so the ledger, the
 * subledgers and the stock all start from the same picture.
 */
beforeEach(function () {
    $this->company = makeCompany();
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id, 'name' => 'Golden Harvest', 'terms_days' => 15]);
    $this->vendor = Vendor::factory()->create(['company_id' => $this->company->id, 'name' => 'Rice Trader']);
    $this->rice = Item::factory()->create([
        'company_id' => $this->company->id, 'sku' => 'RICE-25', 'name' => 'Rice 25kg', 'type' => ItemType::Inventory, 'is_vat_exempt_item' => true,
        'income_account_id' => account($this->company, '4100')->id,
        'cogs_account_id' => account($this->company, '5100')->id,
        'inventory_account_id' => account($this->company, '1300')->id,
    ]);
});

function ledgerBalanceOf($company, string $code): int
{
    return (int) DB::table('journal_lines')
        ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
        ->where('journal_entries.company_id', $company->id)
        ->where('journal_lines.account_id', account($company, $code)->id)
        ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as b')->value('b');
}

it('posts the whole cutover in one go, offset to Opening Balance Equity', function () {
    $result = app(OpeningBalanceSetup::class)->handle($this->company, '2025-12-31', [
        'balances' => [
            account($this->company, '1120')->id => ['debit' => 500_000_00, 'credit' => 0],
            account($this->company, '2200')->id => ['debit' => 0, 'credit' => 60_000_00],
        ],
        'invoices' => [
            ['customer_id' => $this->customer->id, 'number' => 'SI-0871', 'date' => '2025-12-10', 'due_date' => '2025-12-25', 'amount' => 45_000_00],
            ['customer_id' => $this->customer->id, 'number' => 'SI-0902', 'date' => '2025-12-28', 'due_date' => null, 'amount' => 15_000_00],
        ],
        'bills' => [
            ['vendor_id' => $this->vendor->id, 'number' => 'RT-2288', 'date' => '2025-12-20', 'due_date' => '2026-01-19', 'amount' => 130_000_00],
        ],
        'stock' => [
            ['item_id' => $this->rice->id, 'qty' => '120', 'unit_cost' => 1_250_00],
        ],
    ], $this->owner);

    // Cash 500,000 + AR 60,000 + stock 150,000 − VAT 60,000 − AP 130,000 = 520,000 of equity.
    expect(ledgerBalanceOf($this->company, '1120'))->toBe(500_000_00)
        ->and(ledgerBalanceOf($this->company, '1200'))->toBe(60_000_00)
        ->and(ledgerBalanceOf($this->company, '1300'))->toBe(150_000_00)
        ->and(ledgerBalanceOf($this->company, '2100'))->toBe(-130_000_00)
        ->and(ledgerBalanceOf($this->company, '2200'))->toBe(-60_000_00)
        ->and(ledgerBalanceOf($this->company, '3950'))->toBe(-520_000_00)
        ->and(ledgerBalanceOf($this->company, '4100'))->toBe(0);

    expect($result['entry'])->toBeInstanceOf(JournalEntry::class)
        ->and($result['invoices'])->toHaveCount(2)
        ->and($result['bills'])->toHaveCount(1)
        ->and($result['stock'])->toHaveCount(1);

    $invoice = Invoice::query()->where('reference_no', 'SI-0871')->sole();
    expect($invoice->is_opening)->toBeTrue()
        ->and($invoice->status)->toBe(InvoiceStatus::Posted)
        ->and($invoice->due_date->toDateString())->toBe('2025-12-25')
        ->and($invoice->outstanding())->toBe(45_000_00)
        ->and(Invoice::query()->where('reference_no', 'SI-0902')->sole()->due_date->toDateString())->toBe('2026-01-12'); // customer terms
    expect(Bill::query()->where('external_reference_no', 'RT-2288')->sole()->outstanding())->toBe(130_000_00)
        ->and(app(InventoryService::class)->onHand($this->rice))->toMatchArray(['qty_units' => 120 * 10000, 'value' => 150_000_00]);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('refuses balances on the control and stock accounts the other steps feed', function () {
    expect(fn () => app(OpeningBalanceSetup::class)->handle($this->company, '2025-12-31', [
        'balances' => [account($this->company, '1200')->id => ['debit' => 1_000_00, 'credit' => 0]],
    ], $this->owner))->toThrow(RuntimeException::class, 'open invoices');

    expect(fn () => app(OpeningBalanceSetup::class)->handle($this->company, '2025-12-31', [
        'balances' => [account($this->company, '1300')->id => ['debit' => 1_000_00, 'credit' => 0]],
    ], $this->owner))->toThrow(RuntimeException::class, 'stock on hand');

    expect(fn () => app(OpeningBalanceSetup::class)->handle($this->company, '2025-12-31', [], $this->owner))
        ->toThrow(RuntimeException::class, 'nothing');
});

it('walks through the wizard on the Opening Balances page', function () {
    $this->actingAs($this->owner);
    Filament::setTenant($this->company);
    Repeater::fake();
    $cash = account($this->company, '1120')->id;

    Livewire::test(OpeningBalances::class)
        ->assertSee('Opening Balances')
        ->fillForm([
            'opening_date' => '2025-12-31',
            "balances.{$cash}.debit" => '250000',
            'invoices' => [['customer_id' => $this->customer->id, 'number' => 'SI-1', 'date' => '2025-12-15', 'due_date' => '2025-12-30', 'amount' => '10000']],
            'bills' => [['vendor_id' => $this->vendor->id, 'number' => 'B-1', 'date' => '2025-12-20', 'due_date' => '2026-01-20', 'amount' => '4000']],
            'stock' => [['item_id' => $this->rice->id, 'qty' => '10', 'unit_cost' => '1200']],
        ])
        ->call('post')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(ledgerBalanceOf($this->company, '1120'))->toBe(250_000_00)
        ->and(ledgerBalanceOf($this->company, '1200'))->toBe(10_000_00)
        ->and(ledgerBalanceOf($this->company, '2100'))->toBe(-4_000_00)
        ->and(ledgerBalanceOf($this->company, '1300'))->toBe(12_000_00)
        ->and(ledgerBalanceOf($this->company, '3950'))->toBe(-268_000_00)
        ->and(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);

    // The page says so the next time round.
    Livewire::test(OpeningBalances::class)->assertSee('Opening balances were posted on Dec 31, 2025');
});
