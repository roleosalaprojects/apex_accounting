<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Imports\ItemImporter;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Support\ImportCsvAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItems extends ListRecords
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportCsvAction::make(ItemImporter::class, 'Import items')->visible(fn (): bool => ItemResource::canCreate()),
            CreateAction::make(),
        ];
    }
}
