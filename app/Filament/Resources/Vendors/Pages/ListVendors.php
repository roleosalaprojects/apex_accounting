<?php

declare(strict_types=1);

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Imports\VendorImporter;
use App\Filament\Resources\Vendors\VendorResource;
use App\Filament\Support\ImportCsvAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVendors extends ListRecords
{
    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportCsvAction::make(VendorImporter::class, 'Import vendors')->visible(fn (): bool => VendorResource::canCreate()),
            CreateAction::make(),
        ];
    }
}
