<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\RelationManagers;

use App\Filament\Support\PrintAction;
use App\Filament\Support\ShownOnViewPage;
use App\Models\Delivery;
use App\Models\DeliveryLine;
use App\Services\Printing\PrintDeliveryReceipt;
use App\Support\Quantity;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** The delivery receipts issued against the order, each printable. */
class DeliveriesRelationManager extends RelationManager
{
    use ShownOnViewPage;

    protected static string $relationship = 'deliveries';

    protected static ?string $title = 'Delivery receipts';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('lines'))
            ->defaultSort('delivery_date', 'desc')
            ->columns([
                TextColumn::make('number')->label('DR #'),
                TextColumn::make('delivery_date')->label('Date')->date('M j, Y')->sortable(),
                TextColumn::make('lines')->label('Delivered')->wrap()
                    ->state(fn (Delivery $record): string => $record->lines
                        ->map(fn (DeliveryLine $line): string => Quantity::compact(Quantity::toUnits($line->qty)).' × '.$line->description)
                        ->join(', ')),
                TextColumn::make('received_by')->label('Received by')->placeholder('—'),
            ])
            ->recordActions([
                PrintAction::make('pdf', 'Print DR',
                    fn (Delivery $delivery): string => app(PrintDeliveryReceipt::class)->render($delivery),
                    fn (Delivery $delivery): string => ($delivery->number ?? "DR-{$delivery->id}").'.pdf'),
            ])
            ->emptyStateHeading('Nothing delivered yet')
            ->emptyStateDescription('Use Deliver above to issue a delivery receipt for part or all of the order.');
    }
}
