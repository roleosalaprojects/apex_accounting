<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Filament\Pages\Reports\Dunning;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Reports\DunningReport;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('reports overdue balances and flags over-limit customers', function () {
    $company = makeCompany();
    $customer = Customer::factory()->create(['company_id' => $company->id, 'credit_limit' => 50_000_00]);

    Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Posted,
        'invoice_date' => '2026-01-01',
        'due_date' => '2026-01-31',
        'total' => 100_000_00,
        'number' => 'INV-DUN-1',
    ]);

    $r = app(DunningReport::class)->build($company->id, '2026-06-30');

    expect($r['total_outstanding'])->toBe(100_000_00)
        ->and($r['total_overdue'])->toBe(100_000_00)
        ->and($r['rows'][0]['over_limit'])->toBeTrue()
        ->and($r['rows'][0]['oldest_due'])->toBe('2026-01-31');
});

it('renders the dunning report page', function () {
    $company = makeCompany();
    $this->actingAs(makeUserWithRole($company, CompanyRole::Owner));
    Filament::setTenant($company);

    Livewire::test(Dunning::class)->set('asOf', '2026-06-30')->assertOk();
});
