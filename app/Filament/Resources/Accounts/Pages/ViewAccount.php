<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Pages\Reports\GeneralLedger;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Support\Widgets\LedgerActivity;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAccount extends ViewRecord
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generalLedger')
                ->label('General ledger')
                ->icon('heroicon-o-book-open')
                ->color('gray')
                ->visible(fn (): bool => GeneralLedger::canAccess())
                ->url(fn (): string => GeneralLedger::getUrl(['account' => $this->getRecord()->getKey()])),
            EditAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [LedgerActivity::class];
    }
}
