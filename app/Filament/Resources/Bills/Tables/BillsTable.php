<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bills\Tables;

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
                TextColumn::make('exempt_purchases')->label('Exempt')->money('PHP', divideBy: 100)->sortable(),
                TextColumn::make('vatable_purchases')->label('VATable')->money('PHP', divideBy: 100)->sortable(),
                TextColumn::make('input_vat')->label('Input VAT')->money('PHP', divideBy: 100)->sortable(),
                TextColumn::make('total')->money('PHP', divideBy: 100)->sortable(),
            ])
            ->defaultSort('bill_date', 'desc');
    }
}
