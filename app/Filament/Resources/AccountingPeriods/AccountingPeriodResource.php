<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccountingPeriods;

use App\Actions\Ledger\ClosePeriod;
use App\Actions\Ledger\ReopenPeriod;
use App\Enums\PeriodStatus;
use App\Filament\Resources\AccountingPeriods\Pages\ListAccountingPeriods;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Period close/reopen (§4.1). Closing recomputes the balance chain; a locked
 * (year-end) period never reopens. Gated to roles that can approve.
 */
class AccountingPeriodResource extends Resource
{
    protected static ?string $model = AccountingPeriod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Ledger';

    protected static ?string $navigationLabel = 'Periods';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fiscal_year')->label('Year')->sortable(),
                TextColumn::make('period_no')->label('Period')->sortable(),
                TextColumn::make('starts_on')->date(),
                TextColumn::make('ends_on')->date(),
                TextColumn::make('status')->badge()
                    ->color(fn (PeriodStatus $state): string => match ($state) {
                        PeriodStatus::Open => 'success',
                        PeriodStatus::Closed => 'warning',
                        PeriodStatus::Locked => 'danger',
                    }),
                TextColumn::make('journal_entries_count')->counts('journalEntries')->label('Entries'),
            ])
            ->recordActions([
                Action::make('close')->label('Close')->icon('heroicon-o-lock-closed')->color('warning')
                    ->visible(fn (AccountingPeriod $record): bool => $record->status === PeriodStatus::Open && self::userCanManagePeriods())
                    ->requiresConfirmation()
                    ->action(function (AccountingPeriod $record): void {
                        try {
                            app(ClosePeriod::class)->handle($record);
                            Notification::make()->success()->title('Period closed')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Failed')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('reopen')->label('Reopen')->icon('heroicon-o-lock-open')
                    ->visible(fn (AccountingPeriod $record): bool => $record->status === PeriodStatus::Closed && self::userCanManagePeriods())
                    ->requiresConfirmation()
                    ->action(function (AccountingPeriod $record): void {
                        try {
                            app(ReopenPeriod::class)->handle($record);
                            Notification::make()->success()->title('Period reopened')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Failed')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->defaultSort('starts_on');
    }

    public static function userCanManagePeriods(): bool
    {
        /** @var Company|null $company */
        $company = Filament::getTenant();
        /** @var User|null $user */
        $user = Auth::user();

        return $company !== null && $user?->roleIn($company->id)?->canApprove() === true;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountingPeriods::route('/'),
        ];
    }
}
