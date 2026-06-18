<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Filament\Resources\Bills\BillResource;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Throwable;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('PO #')->prefix('PO-')->sortable(),
                TextColumn::make('vendor.name')->searchable()->sortable(),
                TextColumn::make('order_date')->date()->sortable(),
                TextColumn::make('subtotal')->label('Subtotal')->alignEnd()
                    ->state(fn (PurchaseOrder $r): string => number_format($r->subtotal() / 100, 2)),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'billed' => 'success',
                        'cancelled' => 'danger',
                        'received' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('bill.number')->label('Bill')->placeholder('—'),
            ])
            ->defaultSort('order_date', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft', 'sent' => 'Sent', 'received' => 'Received',
                    'billed' => 'Billed', 'cancelled' => 'Cancelled',
                ]),
            ])
            ->recordActions([
                Action::make('convert')
                    ->label('Convert to Bill')->icon('heroicon-o-document-plus')->color('success')
                    ->visible(fn (PurchaseOrder $r): bool => ! in_array($r->status, ['billed', 'cancelled'], true))
                    ->requiresConfirmation()
                    ->action(function (PurchaseOrder $record) {
                        try {
                            $bill = app(PurchaseOrderService::class)->convertToBill($record, Auth::user());
                            Notification::make()->success()->title("Billed as {$bill->number}")->send();

                            return redirect(BillResource::getUrl('view', ['record' => $bill]));
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Could not convert')->body($e->getMessage())->send();

                            return null;
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
