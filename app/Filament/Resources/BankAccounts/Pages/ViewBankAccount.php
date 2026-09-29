<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankAccounts\Pages;

use App\Filament\Pages\Reports\GeneralLedger;
use App\Filament\Resources\BankAccounts\BankAccountResource;
use App\Filament\Support\Widgets\LedgerActivity;
use App\Models\BankAccount;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBankAccount extends ViewRecord
{
    protected static string $resource = BankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generalLedger')
                ->label('General ledger')
                ->icon('heroicon-o-book-open')
                ->color('gray')
                ->visible(fn (): bool => GeneralLedger::canAccess())
                ->url(function (): string {
                    /** @var BankAccount $bankAccount */
                    $bankAccount = $this->getRecord();

                    return GeneralLedger::getUrl(['account' => $bankAccount->account_id]);
                }),
            EditAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [LedgerActivity::class];
    }
}
