<?php

declare(strict_types=1);

namespace App\Filament\Resources\VendorPayments\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VendorPaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('voucher_no')->label('Voucher')->searchable(),
                TextColumn::make('vendor.name')->label('Vendor')->searchable(),
                TextColumn::make('payment_date')->date()->sortable(),
                TextColumn::make('gross_applied')->label('Gross')->money('PHP', divideBy: 100),
                TextColumn::make('ewt')->label('EWT')->money('PHP', divideBy: 100),
                TextColumn::make('net_paid')->label('Net paid')->money('PHP', divideBy: 100),
            ])
            ->defaultSort('payment_date', 'desc');
    }
}
