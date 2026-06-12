<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reconciliations;

use App\Filament\Resources\Reconciliations\Pages\ListReconciliations;
use App\Filament\Resources\Reconciliations\Pages\ManageReconciliation;
use App\Models\Reconciliation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReconciliationResource extends Resource
{
    protected static ?string $model = Reconciliation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'Banking';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false; // started via the Start Reconciliation action
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bankAccount.account.name')->label('Bank account'),
                TextColumn::make('statement_date')->date()->sortable(),
                TextColumn::make('statement_ending_balance')->label('Statement balance')->money('PHP', divideBy: 100),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => $state === 'completed' ? 'success' : 'warning'),
                TextColumn::make('items_count')->counts('items')->label('Lines'),
            ])
            ->recordUrl(fn (Reconciliation $record): string => ManageReconciliation::getUrl(['record' => $record]))
            ->defaultSort('statement_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReconciliations::route('/'),
            'manage' => ManageReconciliation::route('/{record}'),
        ];
    }
}
