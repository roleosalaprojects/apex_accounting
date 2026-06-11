<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Customer')->searchable(),
                TextColumn::make('invoice_date')->date()->sortable(),
                TextColumn::make('status')->badge()->searchable(),
                TextColumn::make('exempt_sales')->label('Exempt')->money('PHP', divideBy: 100)->sortable(),
                TextColumn::make('vatable_sales')->label('VATable')->money('PHP', divideBy: 100)->sortable(),
                TextColumn::make('vat_amount')->label('VAT')->money('PHP', divideBy: 100)->sortable(),
                TextColumn::make('total')->money('PHP', divideBy: 100)->sortable(),
            ])
            ->defaultSort('invoice_date', 'desc');
    }
}
