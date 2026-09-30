<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Actions;

use App\Filament\Support\LineQuantities;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\Sales\SalesOrderService;
use App\Support\Quantity;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Auth;
use Throwable;

/** Ship part (or all) of the order on a numbered delivery receipt. */
final class DeliverAction
{
    public static function make(): Action
    {
        return Action::make('deliver')
            ->label('Deliver')->icon('heroicon-o-truck')->color('info')
            ->authorize('deliver')
            ->visible(fn (SalesOrder $record): bool => $record->status !== 'cancelled'
                && $record->lines->contains(fn (SalesOrderLine $line): bool => $line->unitsToDeliver() > 0))
            ->modalHeading(fn (SalesOrder $record): string => "Deliver against {$record->number}")
            ->modalSubmitActionLabel('Issue delivery receipt')
            ->schema(fn (SalesOrder $record): array => [
                DatePicker::make('delivery_date')->label('Delivery date')->default(now())->required(),
                TextInput::make('received_by')->label('Received by')->maxLength(120),
                Section::make('Quantities')->description('What goes out on this trip; blank lines stay open.')
                    ->schema(LineQuantities::inputs(
                        $record->lines,
                        fn (SalesOrderLine $line): int => $line->unitsToDeliver(),
                        $record->lines->mapWithKeys(fn (SalesOrderLine $line): array => [$line->id => Quantity::compact(max(0, $line->unitsToDeliver()))])->all(),
                        'to deliver',
                    )),
                Textarea::make('notes')->rows(2),
            ])
            ->action(function (SalesOrder $record, array $data): void {
                try {
                    $delivery = app(SalesOrderService::class)->deliver(
                        $record, LineQuantities::read($data), $data['delivery_date'], $data['received_by'] ?? null, $data['notes'] ?? null, Auth::user(),
                    );
                    Notification::make()->success()->title("Delivery receipt {$delivery->number} issued")->send();
                } catch (Throwable $e) {
                    Notification::make()->danger()->title('Could not deliver')->body($e->getMessage())->send();
                }
            });
    }
}
