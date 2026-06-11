<?php

declare(strict_types=1);

use App\Actions\Admin\ExportCompanyData;
use App\Enums\CompanyRole;
use App\Filament\Resources\Bills\BillResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Models\LoginEvent;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;

it('serves the admin login page', function () {
    $this->get('/admin/login')->assertOk();
});

it('registers resources; journal entries, invoices and bills are all creatable (posted docs stay immutable)', function () {
    $resources = Filament::getPanel('admin')->getResources();

    expect(count($resources))->toBeGreaterThanOrEqual(11)
        ->and(JournalEntryResource::canCreate())->toBeTrue()
        ->and(JournalEntryResource::canDeleteAny())->toBeFalse()
        ->and(InvoiceResource::canCreate())->toBeTrue()
        ->and(BillResource::canCreate())->toBeTrue();
});

it('records login events for the security log', function () {
    $company = makeCompany();
    $user = makeUserWithRole($company, CompanyRole::Owner);

    event(new Login('web', $user, false));

    expect(LoginEvent::query()->where('user_id', $user->id)->where('result', 'success')->count())->toBe(1);
});

it('lets an owner export company data as a zip, but blocks non-owners', function () {
    $company = makeCompany();
    $owner = makeUserWithRole($company, CompanyRole::Owner);
    $bookkeeper = makeUserWithRole($company, CompanyRole::Bookkeeper);

    $path = app(ExportCompanyData::class)->handle($company, $owner);

    expect(file_exists($path))->toBeTrue();
    $zip = new ZipArchive;
    $zip->open($path);
    expect($zip->locateName('manifest.json'))->not->toBeFalse()
        ->and($zip->locateName('accounts.csv'))->not->toBeFalse();
    $zip->close();
    @unlink($path);

    expect(fn () => app(ExportCompanyData::class)->handle($company, $bookkeeper))
        ->toThrow(RuntimeException::class);
});
