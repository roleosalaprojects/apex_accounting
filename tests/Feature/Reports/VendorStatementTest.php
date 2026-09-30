<?php

declare(strict_types=1);

use App\Actions\Payables\ApplyDebitMemo;
use App\Actions\Payables\PayBill;
use App\Actions\Payables\PostBill;
use App\Actions\Payables\PostDebitMemo;
use App\Actions\Payables\VoidBill;
use App\Data\Payables\BillData;
use App\Data\Payables\DebitMemoData;
use App\Data\Payables\PayBillData;
use App\Enums\CompanyRole;
use App\Filament\Pages\Reports\VendorStatementPage;
use App\Filament\Resources\Vendors\Pages\ViewVendor;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Services\Printing\PrintVendorStatement;
use App\Services\Reports\VendorStatement;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * The vendor's statement mirrors the customer's: what they billed us, what
 * we paid and what debit memos settled, opening and closing balances.
 */
it('lists bills, payments and debit memos, and leaves voided bills out', function () {
    $company = makeCompany();
    $vendor = Vendor::factory()->create(['company_id' => $company->id, 'name' => 'Bilis Trucking']);
    $exempt = TaxCode::query()->where('company_id', $company->id)->where('code', 'EXEMPT')->value('id');
    $bill = fn (string $date, int $unit) => app(PostBill::class)->handle(BillData::from([
        'company_id' => $company->id, 'vendor_id' => $vendor->id, 'bill_date' => $date,
        'lines' => [['description' => 'Trucking', 'qty' => '1', 'unit_price' => $unit, 'tax_code_id' => $exempt,
            'expense_or_asset_account_id' => account($company, '6300')->id]],
    ]));

    $march = $bill('2026-03-10', 20_000_00);   // before the period: opening balance
    $june = $bill('2026-06-10', 100_000_00);
    $voided = $bill('2026-06-12', 5_000_00);
    app(VoidBill::class)->handle($voided, 'Duplicate');

    $memo = app(PostDebitMemo::class)->handle(DebitMemoData::from([
        'company_id' => $company->id, 'vendor_id' => $vendor->id, 'memo_date' => '2026-06-20',
        'lines' => [['description' => 'Overbilled trip', 'qty' => '1', 'unit_price' => 10_000_00, 'tax_code_id' => $exempt,
            'expense_or_asset_account_id' => account($company, '6300')->id]],
    ]));
    app(ApplyDebitMemo::class)->handle($memo, [['bill_id' => $june->id, 'amount' => 10_000_00]]);

    app(PayBill::class)->handle(PayBillData::from([
        'company_id' => $company->id, 'vendor_id' => $vendor->id, 'payment_date' => '2026-06-25',
        'paid_from_account_id' => account($company, '1120')->id,
        'applications' => [['bill_id' => $june->id, 'amount' => 50_000_00]],
    ]));

    $statement = app(VendorStatement::class)->build($vendor, '2026-06-01', '2026-06-30');

    expect($statement['opening'])->toBe(20_000_00)
        ->and(array_map(fn (array $r): array => [$r['type'], $r['charge'], $r['credit'], $r['balance']], $statement['rows']))->toBe([
            ['Bill', 100_000_00, 0, 120_000_00],
            ['Debit memo', 0, 10_000_00, 110_000_00],
            ['Payment', 0, 50_000_00, 60_000_00],
        ])
        ->and($statement['closing'])->toBe(60_000_00)
        ->and($march->fresh()->outstanding() + $june->fresh()->outstanding())->toBe(60_000_00);

    $html = view('print.statement', app(PrintVendorStatement::class)->data($vendor, '2026-06-01', '2026-06-30'))->render();
    expect($html)->toContain('VENDOR STATEMENT')->toContain('Bilis Trucking')->toContain('60,000.00')->not->toContain('Amount due');
    expect(str_starts_with(app(PrintVendorStatement::class)->render($vendor, '2026-06-01', '2026-06-30'), '%PDF'))->toBeTrue();

    $this->actingAs(makeUserWithRole($company, CompanyRole::Owner));
    Filament::setTenant($company);
    Livewire::test(VendorStatementPage::class)
        ->set('from', '2026-06-01')->set('asOf', '2026-06-30')->set('entity', (string) $vendor->id)
        ->assertSee('Debit memo')->assertSee('60,000.00');
    Livewire::test(ViewVendor::class, ['record' => $vendor->getRouteKey()])->assertActionVisible('statement');
});
