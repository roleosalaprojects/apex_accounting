<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Enums\PricingMode;
use App\Filament\Support\OrderStatus;
use App\Filament\Support\Peso;
use App\Support\Quantity;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalesOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $qty = fn (string $state): string => Quantity::compact(Quantity::toUnits($state));

        return $schema->columns(1)->components([
            Section::make('Order')->columns(4)->schema([
                TextEntry::make('number')->label('SO #'),
                TextEntry::make('customer.name')->label('Customer'),
                TextEntry::make('order_date')->date(),
                TextEntry::make('expiry_date')->label('Valid until')->date()->placeholder('—'),
                TextEntry::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => OrderStatus::label($state))
                    ->color(fn (string $state): string => OrderStatus::color($state)),
                TextEntry::make('pricing_mode')->label('Pricing')
                    ->formatStateUsing(fn (string $state): string => PricingMode::tryFrom($state)?->getLabel() ?? $state),
                TextEntry::make('reference')->placeholder('—'),
                TextEntry::make('createdBy.name')->label('Prepared by')->placeholder('—'),
                TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Lines')->schema([
                RepeatableEntry::make('lines')->hiddenLabel()->columns(6)->schema([
                    TextEntry::make('description')->columnSpan(2),
                    TextEntry::make('qty')->label('Ordered')->formatStateUsing($qty),
                    TextEntry::make('delivered_qty')->label('Delivered')->formatStateUsing($qty),
                    TextEntry::make('invoiced_qty')->label('Invoiced')->formatStateUsing($qty),
                    TextEntry::make('unit_price')->label('Unit price')
                        ->formatStateUsing(fn (int $state): string => Peso::format($state)),
                ]),
            ]),
            Section::make('Total')->columns(4)->schema([
                TextEntry::make('subtotal')->label('Subtotal')->weight('bold')
                    ->state(fn ($record): string => Peso::format($record->subtotal())),
            ]),
        ]);
    }
}
