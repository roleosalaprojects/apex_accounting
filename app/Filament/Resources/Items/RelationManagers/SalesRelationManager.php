<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items\RelationManagers;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Support\Peso;
use App\Filament\Support\Qty;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Support\Money;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Posted invoice lines for the item, newest first. */
class SalesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoiceLines';

    protected static ?string $title = 'Sales';

    /** Lines have no policy of their own: whoever may list invoices may see them. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, ViewRecord::class)
            && (Filament::auth()->user()?->can('viewAny', Invoice::class) ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select('invoice_lines.*')
                ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
                ->whereIn('invoices.status', [InvoiceStatus::Posted->value, InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Paid->value])
                ->with('invoice.customer')
                ->orderByDesc('invoices.invoice_date')
                ->orderByDesc('invoice_lines.id'))
            ->columns([
                TextColumn::make('invoice.invoice_date')->label('Date')->date('M j, Y'),
                TextColumn::make('invoice.number')->label('Invoice'),
                TextColumn::make('invoice.customer.name')->label('Customer'),
                TextColumn::make('qty')->label('Qty')->alignEnd()
                    ->formatStateUsing(fn (string|int|float $state): string => Qty::decimal($state)),
                TextColumn::make('unit_price')->label('Unit price')->alignEnd()
                    ->formatStateUsing(fn (Money $state): string => Peso::format($state)),
                TextColumn::make('line_total')->label('Amount')->alignEnd()
                    ->formatStateUsing(fn (Money $state): string => Peso::format($state)),
            ])
            ->recordUrl(fn (InvoiceLine $record): string => InvoiceResource::getUrl('view', ['record' => $record->invoice_id]));
    }
}
