<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Filament\Resources\Bills\BillResource;
use App\Filament\Resources\PurchaseOrders\Actions\BillOrderAction;
use App\Filament\Support\EmailAction;
use App\Filament\Support\OrderStatus;
use App\Filament\Support\PrintAction;
use App\Mail\PurchaseOrderMail;
use App\Models\PurchaseOrder;
use App\Models\SentEmail;
use App\Models\User;
use App\Services\Mail\DocumentMailer;
use App\Services\Printing\PrintOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['lines', 'bills']))
            ->columns([
                TextColumn::make('number')->label('PO #')->searchable()->sortable(),
                TextColumn::make('vendor.name')->searchable()->sortable(),
                TextColumn::make('order_date')->date()->sortable(),
                TextColumn::make('subtotal')->label('Subtotal')->alignEnd()
                    ->state(fn (PurchaseOrder $r): string => number_format($r->subtotal() / 100, 2)),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => OrderStatus::label($state))
                    ->color(fn (string $state): string => OrderStatus::color($state)),
                TextColumn::make('progress')->label('Billed')
                    ->state(fn (PurchaseOrder $r): string => OrderStatus::purchaseProgress($r)),
                TextColumn::make('bills')->label('Bills')->placeholder('—')
                    ->state(fn (PurchaseOrder $r): string => $r->bills->pluck('number')->join(', '))
                    ->url(fn (PurchaseOrder $r): ?string => $r->bill_id === null ? null : BillResource::getUrl('view', ['record' => $r->bill_id])),
            ])
            ->defaultSort('order_date', 'desc')
            ->filters([
                SelectFilter::make('status')->options(OrderStatus::PURCHASE),
            ])
            ->recordActions([
                PrintAction::make('pdf', 'PDF',
                    fn (PurchaseOrder $order): string => app(PrintOrder::class)->purchaseOrder($order),
                    fn (PurchaseOrder $order): string => ($order->number ?? "PO-{$order->id}").'.pdf'),
                EmailAction::make('email', 'Email',
                    fn (PurchaseOrder $order): array => [
                        'to' => array_filter([$order->vendor->email]),
                        'subject' => PurchaseOrderMail::defaultSubject($order),
                        'message' => PurchaseOrderMail::defaultMessage($order),
                    ],
                    fn (PurchaseOrder $order, array $data, User $user): SentEmail => app(DocumentMailer::class)->purchaseOrder($order, $data['to'], $data['message'], $user),
                )->visible(fn (PurchaseOrder $order): bool => $order->status !== 'cancelled'),
                BillOrderAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
