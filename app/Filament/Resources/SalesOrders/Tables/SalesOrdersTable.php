<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Tables;

use App\Filament\Resources\SalesOrders\Actions\DeliverAction;
use App\Filament\Resources\SalesOrders\Actions\InvoiceOrderAction;
use App\Filament\Support\OrderStatus;
use App\Filament\Support\PrintAction;
use App\Models\SalesOrder;
use App\Services\Printing\PrintOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('lines'))
            ->columns([
                TextColumn::make('number')->label('SO #')->searchable()->sortable(),
                TextColumn::make('customer.name')->searchable()->sortable(),
                TextColumn::make('order_date')->date()->sortable(),
                TextColumn::make('subtotal')->label('Subtotal')->alignEnd()
                    ->state(fn (SalesOrder $r): string => number_format($r->subtotal() / 100, 2)),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => OrderStatus::label($state))
                    ->color(fn (string $state): string => OrderStatus::color($state)),
                TextColumn::make('progress')->label('Delivered · Invoiced')
                    ->state(fn (SalesOrder $r): string => OrderStatus::salesProgress($r)),
                TextColumn::make('invoice.number')->label('Latest invoice')->placeholder('—'),
            ])
            ->defaultSort('order_date', 'desc')
            ->filters([
                SelectFilter::make('status')->options(OrderStatus::SALES),
            ])
            ->recordActions([
                ViewAction::make(),
                PrintAction::make('pdf', 'PDF',
                    fn (SalesOrder $order): string => app(PrintOrder::class)->salesOrder($order),
                    fn (SalesOrder $order): string => ($order->number ?? "SO-{$order->id}").'.pdf'),
                DeliverAction::make(),
                InvoiceOrderAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
