<?php

declare(strict_types=1);

namespace App\Filament\Resources\DebitMemos\Pages;

use App\Filament\Resources\DebitMemos\DebitMemoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDebitMemos extends ListRecords
{
    protected static string $resource = DebitMemoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
