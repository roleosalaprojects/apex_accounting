<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Actions;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Support\LineQuantities;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\Sales\SalesOrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Auth;
use Throwable;

/** Invoice part (or all) of the order; the boxes start at what was delivered but not yet invoiced. */
final class InvoiceOrderAction
{
    public static function make(): Action
    {
        return Action::make('invoice')
            ->label('Invoice')->icon('heroicon-o-document-plus')->color('success')
            ->authorize('convertToInvoice')
            ->visible(fn (SalesOrder $record): bool => $record->status !== 'cancelled'
                && $record->lines->contains(fn (SalesOrderLine $line): bool => $line->unitsToInvoice() > 0))
            ->modalHeading(fn (SalesOrder $record): string => "Invoice {$record->number}")
            ->modalSubmitActionLabel('Post invoice')
            ->schema(fn (SalesOrder $record): array => [
                DatePicker::make('invoice_date')->label('Invoice date')->default(now())->required(),
                Section::make('Quantities')
                    ->description($record->deliveries()->exists()
                        ? 'Starts at what was delivered but not yet invoiced; blank lines stay open.'
                        : 'Starts at everything still open; blank lines stay open.')
                    ->schema(LineQuantities::inputs(
                        $record->lines,
                        fn (SalesOrderLine $line): int => $line->unitsToInvoice(),
                        app(SalesOrderService::class)->suggestedInvoiceQuantities($record),
                        'to invoice',
                    )),
            ])
            ->action(function (SalesOrder $record, array $data) {
                try {
                    $invoice = app(SalesOrderService::class)->invoice($record, LineQuantities::read($data), Auth::user(), $data['invoice_date']);
                    Notification::make()->success()->title("Invoiced as {$invoice->number}")->send();

                    return redirect(InvoiceResource::getUrl('view', ['record' => $invoice]));
                } catch (Throwable $e) {
                    Notification::make()->danger()->title('Could not invoice')->body($e->getMessage())->send();

                    return null;
                }
            });
    }
}
