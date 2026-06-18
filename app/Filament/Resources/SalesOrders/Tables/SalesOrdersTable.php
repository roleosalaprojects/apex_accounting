<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Tables;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\SalesOrder;
use App\Services\Sales\SalesOrderService;
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

class SalesOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('SO #')->prefix('SO-')->sortable(),
                TextColumn::make('customer.name')->searchable()->sortable(),
                TextColumn::make('order_date')->date()->sortable(),
                TextColumn::make('subtotal')->label('Subtotal')->alignEnd()
                    ->state(fn (SalesOrder $r): string => number_format($r->subtotal() / 100, 2)),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'invoiced' => 'success',
                        'cancelled' => 'danger',
                        'accepted' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('invoice.number')->label('Invoice')->placeholder('—'),
            ])
            ->defaultSort('order_date', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted',
                    'invoiced' => 'Invoiced', 'cancelled' => 'Cancelled',
                ]),
            ])
            ->recordActions([
                Action::make('convert')
                    ->label('Convert to Invoice')->icon('heroicon-o-document-plus')->color('success')
                    ->visible(fn (SalesOrder $r): bool => ! in_array($r->status, ['invoiced', 'cancelled'], true))
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record) {
                        try {
                            $invoice = app(SalesOrderService::class)->convertToInvoice($record, Auth::user());
                            Notification::make()->success()->title("Invoiced as {$invoice->number}")->send();

                            return redirect(InvoiceResource::getUrl('view', ['record' => $invoice]));
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
