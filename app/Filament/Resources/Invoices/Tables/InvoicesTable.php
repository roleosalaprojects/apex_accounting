<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Printing\PrintInvoice;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            ->recordActions([
                Action::make('pdf')->label('PDF')->icon('heroicon-o-document-arrow-down')
                    ->visible(fn (Invoice $record): bool => $record->status !== InvoiceStatus::Draft)
                    ->action(fn (Invoice $record): StreamedResponse => response()->streamDownload(
                        function () use ($record): void {
                            echo app(PrintInvoice::class)->render($record);
                        },
                        ($record->number ?? 'invoice').'.pdf',
                    )),
            ])
            ->defaultSort('invoice_date', 'desc');
    }
}
