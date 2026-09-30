<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\RelationManagers;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Support\DocumentStatus;
use App\Filament\Support\Peso;
use App\Filament\Support\ShownOnViewPage;
use App\Models\Invoice;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** The invoices raised from the order, each opening its own page. */
class InvoicesRelationManager extends RelationManager
{
    use ShownOnViewPage;

    protected static string $relationship = 'invoices';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('invoice_date', 'desc')
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('invoice_date')->label('Date')->date('M j, Y')->sortable(),
                TextColumn::make('total')->alignEnd()
                    ->formatStateUsing(fn (Money $state): string => Peso::format($state)),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (InvoiceStatus $state): string => DocumentStatus::label($state))
                    ->color(fn (InvoiceStatus $state): string => DocumentStatus::color($state)),
            ])
            ->recordUrl(fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Not invoiced yet')
            ->emptyStateDescription('Use Invoice above; the quantities start at what has been delivered.');
    }
}
