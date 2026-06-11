<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerPayments\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerPaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Customer')->searchable(),
                TextColumn::make('payment_date')->date()->sortable(),
                TextColumn::make('method')->badge(),
                TextColumn::make('amount')->money('PHP', divideBy: 100)->sortable(),
                TextColumn::make('ewt_withheld')->label('EWT')->money('PHP', divideBy: 100),
            ])
            ->defaultSort('payment_date', 'desc');
    }
}
