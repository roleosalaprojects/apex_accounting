<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccountingPeriods\Pages;

use App\Actions\Ledger\CloseFiscalYear;
use App\Actions\Ledger\OpenFiscalYear;
use App\Filament\Resources\AccountingPeriods\AccountingPeriodResource;
use App\Models\Company;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ListAccountingPeriods extends ListRecords
{
    protected static string $resource = AccountingPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openFiscalYear')->label('Open Fiscal Year')->icon('heroicon-o-calendar')
                ->visible(fn (): bool => AccountingPeriodResource::userCanManagePeriods())
                ->schema([
                    TextInput::make('fiscal_year')->numeric()->default(now()->year)->required(),
                ])
                ->action(function (array $data): void {
                    /** @var Company $company */
                    $company = Filament::getTenant();

                    try {
                        $periods = app(OpenFiscalYear::class)->handle($company, (int) $data['fiscal_year']);
                        Notification::make()->success()
                            ->title('Fiscal year opened')
                            ->body(count($periods).' periods created.')
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('Could not open fiscal year')->body($e->getMessage())->send();
                    }
                }),
            Action::make('closeFiscalYear')->label('Close Fiscal Year')->icon('heroicon-o-archive-box')->color('danger')
                ->visible(fn (): bool => AccountingPeriodResource::userCanManagePeriods())
                ->requiresConfirmation()
                ->modalDescription('Posts the year-end closing entry (nominal accounts → Retained Earnings) and locks every period of the year. Locked periods never reopen.')
                ->schema([
                    TextInput::make('fiscal_year')->numeric()->default(now()->year - 1)->required(),
                ])
                ->action(function (array $data): void {
                    /** @var Company $company */
                    $company = Filament::getTenant();
                    /** @var User $user */
                    $user = Auth::user();

                    try {
                        $entry = app(CloseFiscalYear::class)->handle($company, (int) $data['fiscal_year'], $user);
                        Notification::make()->success()
                            ->title('Fiscal year closed')
                            ->body($entry !== null ? "Closing entry {$entry->number} posted." : 'No nominal balances to close; periods locked.')
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('Could not close fiscal year')->body($e->getMessage())->send();
                    }
                }),
        ];
    }
}
