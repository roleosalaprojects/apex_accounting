<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerPayments\Tables;

use App\Actions\Receivables\VoidCustomerPayment;
use App\Filament\Support\Peso;
use App\Filament\Support\PrintAction;
use App\Filament\Support\VoidAction;
use App\Models\CustomerPayment;
use App\Models\User;
use App\Services\Printing\PrintCollectionReceipt;
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
                TextColumn::make('amount')->formatStateUsing(fn (mixed $state): ?string => Peso::state($state))->alignEnd()->sortable(),
                TextColumn::make('ewt_withheld')->label('EWT')->formatStateUsing(fn (mixed $state): ?string => Peso::state($state))->alignEnd(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => $state === 'posted' ? 'success' : 'gray'),
            ])
            ->recordActions([
                PrintAction::make('receipt', 'Receipt',
                    fn (CustomerPayment $payment): string => app(PrintCollectionReceipt::class)->render($payment),
                    fn (CustomerPayment $payment): string => ($payment->collection_receipt_no ?? $payment->number).'.pdf')
                    ->visible(fn (CustomerPayment $payment): bool => $payment->status === 'posted'),
                VoidAction::make(
                    'collection',
                    fn (CustomerPayment $payment, string $reason, User $user) => app(VoidCustomerPayment::class)->handle($payment, $reason, $user),
                    fn (CustomerPayment $payment): bool => $payment->status === 'posted',
                ),
            ])
            ->defaultSort('payment_date', 'desc');
    }
}
