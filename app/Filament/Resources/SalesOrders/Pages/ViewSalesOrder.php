<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\SalesOrders\Actions\DeliverAction;
use App\Filament\Resources\SalesOrders\Actions\InvoiceOrderAction;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Support\LoadsRecordRelations;
use App\Filament\Support\PrintAction;
use App\Models\SalesOrder;
use App\Services\Printing\PrintOrder;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesOrder extends ViewRecord
{
    use LoadsRecordRelations;

    protected static string $resource = SalesOrderResource::class;

    protected function recordRelations(): array
    {
        return ['customer', 'lines', 'createdBy'];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeliverAction::make(),
            InvoiceOrderAction::make(),
            Action::make('accept')->label('Accept')->icon('heroicon-o-check')->color('gray')
                ->visible(fn (SalesOrder $order): bool => in_array($order->status, ['draft', 'sent'], true) && SalesOrderResource::canEdit($order))
                ->requiresConfirmation()
                ->modalDescription('Marks the quotation as accepted by the customer; it prints as a sales order from here on.')
                ->action(function (SalesOrder $order): void {
                    $order->update(['status' => 'accepted']);
                    Notification::make()->success()->title("{$order->number} accepted")->send();
                }),
            PrintAction::make('pdf', 'PDF',
                fn (SalesOrder $order): string => app(PrintOrder::class)->salesOrder($order),
                fn (SalesOrder $order): string => ($order->number ?? "SO-{$order->id}").'.pdf'),
            EditAction::make(),
        ];
    }
}
