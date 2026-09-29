<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalEntries\Tables;

use App\Filament\Support\Peso;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JournalEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('entry_date')->date()->sortable(),
                TextColumn::make('memo')->limit(40)->searchable(),
                TextColumn::make('source_type')->label('Source')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : 'Manual')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('total_debits')->label('Amount')->formatStateUsing(fn (mixed $state): ?string => Peso::state($state))->alignEnd()->sortable(),
            ])
            ->defaultSort('entry_date', 'desc');
    }
}
