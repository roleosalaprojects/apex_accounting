<?php

declare(strict_types=1);

use App\Actions\Payables\PostDraftBill;
use App\Actions\Receivables\PostDraftInvoice;
use App\Actions\Receivables\RejectDraftInvoice;
use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Enums\VatBucket;
use App\Exceptions\Ledger\UnapprovedDocumentException;
use App\Filament\Resources\Bills\Pages\CreateBill;
use App\Filament\Resources\Bills\Pages\ViewBill;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\TaxCode;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Livewire;

/*
 * Maker-checker for invoices and bills: whoever cannot post — or anyone,
 * when the company requires approval — saves a draft; a poster who is not
 * the maker approves and posts it, or rejects it with a reason. Everyone
 * involved hears about it through the bell.
 */
beforeEach(function () {
    $this->company = makeCompany(['require_approval' => true]);
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->bookkeeper = makeUserWithRole($this->company, CompanyRole::Bookkeeper);
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id, 'name' => 'Golden Harvest']);
    $this->vendor = Vendor::factory()->create(['company_id' => $this->company->id]);
    $this->exempt = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->value('id');
    $this->vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');
    Repeater::fake();
});

function signIn($test, $user): void
{
    $test->actingAs($user);
    Filament::setTenant($test->company);
}

function draftInvoiceForm($test): array
{
    return [
        'customer_id' => $test->customer->id, 'invoice_date' => '2026-06-15', 'pricing_mode' => 'vat_inclusive',
        'lines' => [['description' => 'Rice 25kg', 'qty' => '10', 'unit_price' => '1450', 'tax_code_id' => $test->exempt,
            'income_account_id' => account($test->company, '4100')->id]],
    ];
}

it('saves a draft for approval, tells the approvers, and lets a different poster post it', function () {
    signIn($this, $this->bookkeeper);

    Livewire::test(CreateInvoice::class)->fillForm(draftInvoiceForm($this))->call('create')->assertHasNoFormErrors();

    $draft = Invoice::query()->sole();
    expect($draft->status)->toBe(InvoiceStatus::Draft)
        ->and($draft->number)->toBeNull()
        ->and($draft->journal_entry_id)->toBeNull()
        ->and($draft->total->minor)->toBe(14_500_00)
        ->and($draft->lines)->toHaveCount(1)
        ->and($draft->created_by)->toBe($this->bookkeeper->id);

    $notification = DatabaseNotification::query()->where('notifiable_id', $this->owner->id)->sole();
    expect($notification->data['title'])->toContain('awaiting approval')
        ->and($notification->data['body'])->toContain('Golden Harvest');
    expect(DatabaseNotification::query()->where('notifiable_id', $this->bookkeeper->id)->exists())->toBeFalse();

    // The maker cannot post it, and neither can a poster who also drafted it while approval is required.
    expect($this->bookkeeper->can('post', $draft))->toBeFalse();
    expect(fn () => app(PostDraftInvoice::class)->handle($draft, $this->bookkeeper))->toThrow(UnapprovedDocumentException::class);

    signIn($this, $this->owner);
    expect(InvoiceResource::getNavigationBadge())->toBe('1');
    Livewire::test(ViewInvoice::class, ['record' => $draft->getRouteKey()])
        ->assertActionVisible('post')
        ->callAction('post')
        ->assertNotified();

    $posted = $draft->fresh();
    expect($posted->status)->toBe(InvoiceStatus::Posted)
        ->and($posted->number)->toBe('INV-2026-000001')
        ->and($posted->journal_entry_id)->not->toBeNull()
        ->and($posted->approved_by)->toBe($this->owner->id)
        ->and($posted->created_by)->toBe($this->bookkeeper->id)
        ->and((int) $posted->journalEntry->lines->sum(fn ($line): int => $line->debit->minor))->toBe(14_500_00);
    expect(DatabaseNotification::query()->where('notifiable_id', $this->bookkeeper->id)->sole()->data['title'])->toContain('posted');
    expect(InvoiceResource::getNavigationBadge())->toBeNull();
});

it('refuses self-approval while approval is required, but lets a poster post straight away when it is not', function () {
    signIn($this, $this->owner);
    Livewire::test(CreateInvoice::class)->fillForm(draftInvoiceForm($this))->call('create')->assertHasNoFormErrors();
    $draft = Invoice::query()->sole();
    expect($draft->status)->toBe(InvoiceStatus::Draft);
    expect(fn () => app(PostDraftInvoice::class)->handle($draft, $this->owner))->toThrow(UnapprovedDocumentException::class, 'own');

    $this->company->update(['require_approval' => false]);
    Livewire::test(CreateInvoice::class)->fillForm(draftInvoiceForm($this))->call('create')->assertHasNoFormErrors();
    expect(Invoice::query()->where('status', InvoiceStatus::Posted)->count())->toBe(1);

    // With approval off, the earlier draft can now be posted by its maker.
    app(PostDraftInvoice::class)->handle($draft->fresh(), $this->owner);
    expect($draft->fresh()->status)->toBe(InvoiceStatus::Posted);
});

it('rejects a draft with a reason, which reaches the maker', function () {
    signIn($this, $this->bookkeeper);
    Livewire::test(CreateInvoice::class)->fillForm(draftInvoiceForm($this))->call('create');
    $draft = Invoice::query()->sole();

    signIn($this, $this->owner);
    Livewire::test(ViewInvoice::class, ['record' => $draft->getRouteKey()])
        ->callAction('reject', ['reason' => 'Wrong price — should be ₱1,420'])
        ->assertNotified();

    expect(Invoice::query()->count())->toBe(0);
    $notice = DatabaseNotification::query()->where('notifiable_id', $this->bookkeeper->id)->sole();
    expect($notice->data['title'])->toContain('rejected')->and($notice->data['body'])->toContain('Wrong price');

    expect(fn () => app(RejectDraftInvoice::class)->handle(Invoice::factory()->make(['status' => InvoiceStatus::Posted]), 'x', $this->owner))
        ->toThrow(RuntimeException::class);
});

it('works the same way for bills', function () {
    signIn($this, $this->bookkeeper);
    Livewire::test(CreateBill::class)
        ->fillForm([
            'vendor_id' => $this->vendor->id, 'bill_date' => '2026-06-05', 'pricing_mode' => 'vat_inclusive',
            'lines' => [['description' => 'Office rent', 'qty' => '1', 'unit_price' => '56000', 'tax_code_id' => $this->vat12,
                'vat_bucket' => VatBucket::Common->value, 'expense_or_asset_account_id' => account($this->company, '6100')->id]],
        ])
        ->call('create')->assertHasNoFormErrors();

    $draft = Bill::query()->sole();
    expect($draft->status)->toBe(InvoiceStatus::Draft)->and($draft->number)->toBeNull()->and($draft->total->minor)->toBe(56_000_00);
    expect(fn () => app(PostDraftBill::class)->handle($draft, $this->bookkeeper))->toThrow(UnapprovedDocumentException::class);

    signIn($this, $this->owner);
    Livewire::test(ViewBill::class, ['record' => $draft->getRouteKey()])->callAction('post')->assertNotified();
    $posted = $draft->fresh();
    expect($posted->status)->toBe(InvoiceStatus::Posted)
        ->and($posted->number)->toBe('BILL-2026-000001')
        ->and($posted->journalEntry->lines->firstWhere('account_id', account($this->company, '1410')->id)->debit->minor)->toBe(6_000_00);
});
