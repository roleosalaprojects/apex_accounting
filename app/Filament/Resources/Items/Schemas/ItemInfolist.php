<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items\Schemas;

use App\Enums\ItemType;
use App\Filament\Support\FiscalYear;
use App\Filament\Support\KeyFigure;
use App\Filament\Support\Peso;
use App\Filament\Support\Qty;
use App\Models\Account;
use App\Models\Item;
use App\Services\Reports\ItemSummary;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Summary')->columns(['default' => 2, 'lg' => 4])->schema([
                KeyFigure::make('on_hand', 'On hand')
                    ->visible(fn (Item $record): bool => $record->type->tracksStock())
                    ->state(fn (Item $record): string => Qty::units(self::summary($record)['on_hand']['qty_units']))
                    ->belowContent(fn (Item $record): string => 'Average cost '
                        .Peso::format((int) round(self::summary($record)['on_hand']['avg_cost_x10000'] / 10_000))
                        .' per '.$record->unit),
                KeyFigure::make('stock_value', 'Stock value')
                    ->visible(fn (Item $record): bool => $record->type->tracksStock())
                    ->state(fn (Item $record): string => Peso::format(self::summary($record)['on_hand']['value']))
                    ->belowContent('At weighted-average cost'),
                KeyFigure::make('sales', 'Sold this year')
                    ->state(fn (Item $record): string => Peso::format(self::summary($record)['sales']))
                    ->belowContent(fn (Item $record): string => Qty::units(self::summary($record)['sold_units'])
                        .' '.$record->unit.' since '.FiscalYear::start()->format('M j, Y').', net of VAT'),
                KeyFigure::make('purchases', 'Bought this year')
                    ->state(fn (Item $record): string => Peso::format(self::summary($record)['purchases']))
                    ->belowContent(fn (Item $record): string => Qty::units(self::summary($record)['bought_units'])
                        .' '.$record->unit.' since '.FiscalYear::start()->format('M j, Y').', net of VAT'),
            ])->columnSpanFull(),
            Section::make('Details')->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                TextEntry::make('sku')->label('SKU'),
                TextEntry::make('name'),
                TextEntry::make('type')
                    ->formatStateUsing(fn (ItemType $state): string => ucfirst(str_replace('_', '-', $state->value))),
                TextEntry::make('unit'),
                TextEntry::make('default_sales_price')->label('Sales price')
                    ->formatStateUsing(fn (Money $state): string => Peso::format($state)),
                TextEntry::make('default_purchase_price')->label('Purchase price')
                    ->formatStateUsing(fn (Money $state): string => Peso::format($state)),
                IconEntry::make('is_vat_exempt_item')->label('VAT-exempt')->boolean(),
                IconEntry::make('is_active')->label('Active')->boolean(),
                TextEntry::make('incomeAccount.name')->label('Income account')
                    ->formatStateUsing(fn (Item $record): string => self::account($record->incomeAccount))
                    ->placeholder('—'),
                TextEntry::make('cogsAccount.name')->label('Cost of sales account')
                    ->formatStateUsing(fn (Item $record): string => self::account($record->cogsAccount))
                    ->placeholder('—'),
                TextEntry::make('inventoryAccount.name')->label('Inventory account')
                    ->formatStateUsing(fn (Item $record): string => self::account($record->inventoryAccount))
                    ->placeholder('—'),
            ])->columnSpanFull(),
        ]);
    }

    /**
     * @return array{on_hand: array{qty_units: int, avg_cost_x10000: int, value: int}, sold_units: int, sales: int, bought_units: int, purchases: int}
     */
    private static function summary(Item $item): array
    {
        return once(fn (): array => app(ItemSummary::class)->build(
            $item,
            FiscalYear::start()->toDateString(),
            CarbonImmutable::today()->toDateString(),
        ));
    }

    private static function account(?Account $account): string
    {
        return $account === null ? '' : "{$account->code} · {$account->name}";
    }
}
