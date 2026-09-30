<?php

declare(strict_types=1);

namespace App\Filament\Resources\WithholdingCodes\Pages;

use App\Filament\Resources\WithholdingCodes\WithholdingCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWithholdingCodes extends ListRecords
{
    protected static string $resource = WithholdingCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
