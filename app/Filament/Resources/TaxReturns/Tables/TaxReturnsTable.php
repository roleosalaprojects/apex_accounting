<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaxReturns\Tables;

use App\Enums\TaxReturnType;
use App\Models\TaxReturn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TaxReturnsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->badge()
                    ->formatStateUsing(fn (string $state): string => TaxReturnType::tryFrom($state)?->label() ?? $state),
                TextColumn::make('fiscal_year')->label('FY')->sortable(),
                TextColumn::make('quarter')->label('Qtr')->formatStateUsing(fn (?int $state): string => $state ? "Q{$state}" : '—'),
                TextColumn::make('period_start')->label('Period')->date()
                    ->description(fn (TaxReturn $r): string => 'to '.$r->period_end->toDateString()),
                TextColumn::make('headline')->label('Amount due')
                    ->state(fn (TaxReturn $r): string => number_format($r->headlineAmount() / 100, 2)),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => $state === 'filed' ? 'success' : 'gray'),
                TextColumn::make('created_at')->dateTime()->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
