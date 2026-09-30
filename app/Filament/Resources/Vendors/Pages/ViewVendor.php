<?php

declare(strict_types=1);

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Resources\Vendors\VendorResource;
use App\Filament\Support\FiscalYear;
use App\Filament\Support\PrintAction;
use App\Models\Vendor;
use App\Services\Printing\PrintVendorStatement;
use Carbon\CarbonImmutable;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVendor extends ViewRecord
{
    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PrintAction::make('statement', 'Statement',
                fn (Vendor $vendor): string => app(PrintVendorStatement::class)->render(
                    $vendor, FiscalYear::start()->toDateString(), CarbonImmutable::today()->toDateString(),
                ),
                fn (Vendor $vendor): string => 'VS-'.$vendor->code.'-'.CarbonImmutable::today()->format('Ymd').'.pdf',
            )->icon('heroicon-o-document-text'),
            EditAction::make(),
        ];
    }
}
