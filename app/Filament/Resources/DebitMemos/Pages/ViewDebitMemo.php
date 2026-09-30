<?php

declare(strict_types=1);

namespace App\Filament\Resources\DebitMemos\Pages;

use App\Filament\Resources\DebitMemos\Actions\ApplyDebitMemoAction;
use App\Filament\Resources\DebitMemos\DebitMemoResource;
use App\Filament\Support\LoadsRecordRelations;
use Filament\Resources\Pages\ViewRecord;

class ViewDebitMemo extends ViewRecord
{
    use LoadsRecordRelations;

    protected static string $resource = DebitMemoResource::class;

    protected function recordRelations(): array
    {
        return ['vendor', 'lines', 'applications.bill', 'journalEntry'];
    }

    protected function getHeaderActions(): array
    {
        return [ApplyDebitMemoAction::make()];
    }
}
