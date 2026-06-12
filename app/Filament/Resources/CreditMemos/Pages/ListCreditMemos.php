<?php

declare(strict_types=1);

namespace App\Filament\Resources\CreditMemos\Pages;

use App\Filament\Resources\CreditMemos\CreditMemoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCreditMemos extends ListRecords
{
    protected static string $resource = CreditMemoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
