<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Imports\AccountImporter;
use App\Filament\Pages\OpeningBalances;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Support\ImportCsvAction;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openingBalances')
                ->label('Opening Balances')
                ->icon('heroicon-o-scale')
                ->color('gray')
                ->visible(fn (): bool => OpeningBalances::canAccess())
                ->url(fn (): string => OpeningBalances::getUrl()),
            ImportCsvAction::make(AccountImporter::class, 'Import accounts')->visible(fn (): bool => AccountResource::canCreate()),
            CreateAction::make(),
        ];
    }
}
