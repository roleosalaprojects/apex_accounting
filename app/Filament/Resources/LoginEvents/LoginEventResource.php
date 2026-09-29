<?php

declare(strict_types=1);

namespace App\Filament\Resources\LoginEvents;

use App\Filament\Resources\LoginEvents\Pages\ListLoginEvents;
use App\Models\Company;
use App\Models\LoginEvent;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Login history viewer (§13 internal controls). LoginEvents are global (no
 * company_id) so the query is filtered to this company's members.
 */
class LoginEventResource extends Resource
{
    protected static ?string $model = LoginEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Login Events';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        /** @var Company|null $company */
        $company = Filament::getTenant();
        $memberIds = $company?->users()->pluck('users.id') ?? collect();
        $memberEmails = $company?->users()->pluck('users.email') ?? collect();

        return parent::getEloquentQuery()
            ->where(function (Builder $query) use ($memberIds, $memberEmails): void {
                $query->whereIn('user_id', $memberIds)
                    ->orWhereIn('email', $memberEmails);
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('result')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('ip')->label('IP')->placeholder('—'),
                TextColumn::make('user_agent')->label('Agent')->limit(50)->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoginEvents::route('/'),
        ];
    }
}
