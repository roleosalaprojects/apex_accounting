<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Pages;

use App\Actions\Ledger\SetupOpeningBalances;
use App\Data\Ledger\OpeningBalancesData;
use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openingBalances')
                ->label('Opening Balances')
                ->icon('heroicon-o-scale')
                ->visible(function (): bool {
                    /** @var Company|null $company */
                    $company = Filament::getTenant();
                    /** @var User|null $user */
                    $user = Auth::user();

                    return $company !== null && $user?->roleIn($company->id)?->canApprove() === true;
                })
                ->modalDescription('Posts one balanced opening entry dated the day you enter, offset to 3950 Opening Balance Equity. Enter each account\'s balance as a debit or a credit.')
                ->schema([
                    DatePicker::make('opening_date')->required()
                        ->helperText('Usually the day before your first open period.'),
                    Repeater::make('lines')
                        ->minItems(1)
                        ->defaultItems(1)
                        ->columns(3)
                        ->schema([
                            Select::make('account_id')
                                ->label('Account')
                                ->options(fn () => Account::query()
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"]))
                                ->searchable()
                                ->required(),
                            TextInput::make('debit')->label('Debit (₱)')->numeric(),
                            TextInput::make('credit')->label('Credit (₱)')->numeric(),
                        ]),
                ])
                ->action(function (array $data): void {
                    /** @var Company $company */
                    $company = Filament::getTenant();
                    /** @var User $user */
                    $user = Auth::user();

                    $lines = array_map(fn (array $line): array => [
                        'account_id' => (int) $line['account_id'],
                        'debit' => filled($line['debit'] ?? null) ? (int) round(((float) $line['debit']) * 100) : 0,
                        'credit' => filled($line['credit'] ?? null) ? (int) round(((float) $line['credit']) * 100) : 0,
                    ], $data['lines']);

                    try {
                        $entry = app(SetupOpeningBalances::class)->handle(OpeningBalancesData::from([
                            'company_id' => $company->id,
                            'opening_date' => $data['opening_date'],
                            'lines' => $lines,
                            'created_by' => $user->id,
                        ]));
                        Notification::make()->success()
                            ->title('Opening balances posted')
                            ->body("Entry {$entry->number} posted.")
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('Could not post opening balances')->body($e->getMessage())->send();
                    }
                }),
            CreateAction::make(),
        ];
    }
}
