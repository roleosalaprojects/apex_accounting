<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Actions;

use App\Filament\Resources\Bills\BillResource;
use App\Filament\Support\LineQuantities;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\Purchasing\PurchaseOrderService;
use App\Support\Quantity;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Auth;
use Throwable;

/** Bill the part of the order that arrived; stocked items are received as the bill posts. */
final class BillOrderAction
{
    public static function make(): Action
    {
        return Action::make('bill')
            ->label('Bill')->icon('heroicon-o-document-plus')->color('success')
            ->authorize('convertToBill')
            ->visible(fn (PurchaseOrder $record): bool => $record->status !== 'cancelled'
                && $record->lines->contains(fn (PurchaseOrderLine $line): bool => $line->unitsToBill() > 0))
            ->modalHeading(fn (PurchaseOrder $record): string => "Bill {$record->number}")
            ->modalSubmitActionLabel('Post bill')
            ->schema(fn (PurchaseOrder $record): array => [
                DatePicker::make('bill_date')->label('Bill date')->default(now())->required(),
                Section::make('Quantities received')->description('Blank lines stay open for a later bill.')
                    ->schema(LineQuantities::inputs(
                        $record->lines,
                        fn (PurchaseOrderLine $line): int => $line->unitsToBill(),
                        $record->lines->mapWithKeys(fn (PurchaseOrderLine $line): array => [$line->id => Quantity::compact(max(0, $line->unitsToBill()))])->all(),
                        'to bill',
                    )),
            ])
            ->action(function (PurchaseOrder $record, array $data) {
                try {
                    $bill = app(PurchaseOrderService::class)->bill($record, LineQuantities::read($data), Auth::user(), $data['bill_date']);
                    Notification::make()->success()->title("Billed as {$bill->number}")->send();

                    return redirect(BillResource::getUrl('view', ['record' => $bill]));
                } catch (Throwable $e) {
                    Notification::make()->danger()->title('Could not bill')->body($e->getMessage())->send();

                    return null;
                }
            });
    }
}
