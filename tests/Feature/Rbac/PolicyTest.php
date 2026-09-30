<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Enums\RecurringKind;
use App\Filament\Pages\Reports\TrialBalance;
use App\Filament\Pages\Team;
use App\Filament\Pages\Tenancy\EditCompanyProfile;
use App\Filament\Resources\AccountingPeriods\AccountingPeriodResource;
use App\Filament\Resources\AccountingPeriods\Pages\ListAccountingPeriods;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\BankAccounts\BankAccountResource;
use App\Filament\Resources\Bills\BillResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Resources\JournalEntries\Pages\ViewJournalEntry;
use App\Filament\Resources\RecurringTemplates\Pages\EditRecurringTemplate;
use App\Filament\Resources\RecurringTemplates\Pages\ListRecurringTemplates;
use App\Filament\Resources\RecurringTemplates\RecurringTemplateResource;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\AccountingPeriod;
use App\Models\Bill;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\RecurringTemplate;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
});

function actingAsRole(Company $company, CompanyRole $role): User
{
    $user = makeUserWithRole($company, $role);
    test()->actingAs($user);
    Filament::setTenant($company);

    return $user;
}

it('has a policy for every panel resource model (strict authorization)', function () {
    expect(Filament::getPanel('admin')->isAuthorizationStrict())->toBeTrue();

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        expect(Gate::getPolicyFor($resource::getModel()))->not->toBeNull("{$resource} has no policy");
    }
});

it('serves the dashboard and every resource index to an owner', function () {
    actingAsRole($this->company, CompanyRole::Owner);

    $this->get(Dashboard::getUrl())->assertOk();

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        $this->get($resource::getUrl('index'))->assertOk();
    }
});

it('limits a viewer to reports', function () {
    actingAsRole($this->company, CompanyRole::Viewer);

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        expect($resource::canViewAny())->toBeFalse("viewer can list {$resource}");
    }

    expect(TrialBalance::canAccess())->toBeTrue()
        ->and(Team::canAccess())->toBeFalse()
        ->and(EditCompanyProfile::canView($this->company))->toBeFalse();

    $this->get(Dashboard::getUrl())->assertOk();
    $this->get(CustomerResource::getUrl('index'))->assertForbidden();
});

it('keeps company settings to company.manage', function () {
    actingAsRole($this->company, CompanyRole::Accountant);
    expect(EditCompanyProfile::canView($this->company))->toBeFalse();

    actingAsRole($this->company, CompanyRole::Owner);
    expect(EditCompanyProfile::canView($this->company))->toBeTrue();
});

it('lets a bookkeeper keep master data but not open screens that post', function () {
    $bookkeeper = actingAsRole($this->company, CompanyRole::Bookkeeper);

    expect(CustomerResource::canCreate())->toBeTrue()
        ->and(VendorResource::canCreate())->toBeTrue()
        ->and(ItemResource::canCreate())->toBeTrue()
        ->and(RecurringTemplateResource::canCreate())->toBeTrue()
        // A bookkeeper drafts invoices and bills for a poster to approve (maker-checker), but cannot post.
        ->and(InvoiceResource::canViewAny())->toBeTrue()
        ->and(InvoiceResource::canCreate())->toBeTrue()
        ->and(BillResource::canCreate())->toBeTrue()
        ->and($bookkeeper->can('post', new Invoice(['company_id' => $this->company->id])))->toBeFalse()
        ->and($bookkeeper->can('post', new Bill(['company_id' => $this->company->id])))->toBeFalse()
        ->and(JournalEntryResource::canCreate())->toBeFalse()
        ->and(AccountResource::canViewAny())->toBeTrue()
        ->and(AccountResource::canCreate())->toBeFalse()
        ->and(AccountingPeriodResource::canViewAny())->toBeFalse()
        ->and(BankAccountResource::canViewAny())->toBeFalse()
        ->and(DepartmentResource::canViewAny())->toBeFalse();
});

it('keeps reversal and year-end close out of the approver role', function () {
    $entry = postEntry($this->company, '2026-06-10', [
        ['account_id' => account($this->company, '6100')->id, 'debit' => 1_000_00],
        ['account_id' => account($this->company, '1110')->id, 'credit' => 1_000_00],
    ]);
    $june = AccountingPeriod::query()->withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->whereDate('starts_on', '2026-06-01')->firstOrFail();

    actingAsRole($this->company, CompanyRole::Approver);

    Livewire::test(ViewJournalEntry::class, ['record' => $entry->getKey()])->assertActionHidden('reverse');
    Livewire::test(ListAccountingPeriods::class)
        ->assertActionVisible('openFiscalYear')
        ->assertActionHidden('closeFiscalYear')
        ->assertActionVisible(TestAction::make('close')->table($june));
    expect(CustomerResource::canCreate())->toBeFalse()
        ->and(InvoiceResource::canCreate())->toBeFalse();

    actingAsRole($this->company, CompanyRole::Accountant);

    Livewire::test(ViewJournalEntry::class, ['record' => $entry->getKey()])->assertActionVisible('reverse');
    Livewire::test(ListAccountingPeriods::class)->assertActionVisible('closeFiscalYear');
});

it('shows Run Due Templates only to members with recurring.run', function () {
    $bookkeeper = actingAsRole($this->company, CompanyRole::Bookkeeper);
    Livewire::test(ListRecurringTemplates::class)->assertActionHidden('runDue');

    actingAsRole($this->company, CompanyRole::Accountant);
    Livewire::test(ListRecurringTemplates::class)->assertActionVisible('runDue');
});

it('records who last saved a template, so the run posts under their authority', function () {
    $template = RecurringTemplate::factory()->create([
        'company_id' => $this->company->id, 'kind' => RecurringKind::JournalEntry,
        'auto_post' => true, 'payload' => ['memo' => 'Rent', 'lines' => []],
    ]);

    $bookkeeper = actingAsRole($this->company, CompanyRole::Bookkeeper);

    Livewire::test(EditRecurringTemplate::class, ['record' => $template->getKey()])
        ->fillForm(['name' => 'Rent (edited)'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($template->fresh()->updated_by)->toBe($bookkeeper->id);
});
