<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Tenancy\EditCompanyProfile;
use App\Filament\Pages\Tenancy\RegisterCompany;
use App\Http\Middleware\SetCompanyContextFromTenant;
use App\Models\Company;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Infolists\Components\IconEntry;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // A "no" (not a withholding agent, not VAT-exempt) is a fact, not an
        // error: show false booleans in gray rather than red.
        IconColumn::configureUsing(fn (IconColumn $column): IconColumn => $column->falseColor('gray'));
        IconEntry::configureUsing(fn (IconEntry $entry): IconEntry => $entry->falseColor('gray'));
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            // Every resource model must have a policy (app/Policies); a missing
            // one throws instead of silently allowing every company member.
            ->strictAuthorization()
            ->tenant(Company::class)
            ->tenantRegistration(RegisterCompany::class)
            ->tenantProfile(EditCompanyProfile::class)
            ->tenantMiddleware([
                SetCompanyContextFromTenant::class,
            ], isPersistent: true)
            ->brandName('Apex Accounting')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            // Includes the accounting Dashboard (App\Filament\Pages\Dashboard).
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
