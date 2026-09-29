<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bills\Tables;

use App\Filament\Support\Peso;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BillsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('vendor.name')->label('Vendor')->searchable(),
                TextColumn::make('bill_date')->date()->sortable(),
                TextColumn::make('status')->badge()->searchable(),
                TextColumn::make('exempt_purchases')->label('Exempt')->formatStateUsing(fn (mixed $state): ?string => Peso::state($state))->alignEnd()->sortable(),
                TextColumn::make('vatable_purchases')->label('VATable')->formatStateUsing(fn (mixed $state): ?string => Peso::state($state))->alignEnd()->sortable(),
                TextColumn::make('input_vat')->label('Input VAT')->formatStateUsing(fn (mixed $state): ?string => Peso::state($state))->alignEnd()->sortable(),
                TextColumn::make('total')->formatStateUsing(fn (mixed $state): ?string => Peso::state($state))->alignEnd()->sortable(),
            ])
            ->defaultSort('bill_date', 'desc');
    }
}
