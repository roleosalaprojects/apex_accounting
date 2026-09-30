<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items\Pages;

use App\Enums\ItemType;
use App\Filament\Pages\Reports\StockCardPage;
use App\Filament\Resources\Items\ItemResource;
use App\Models\Item;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItem extends ViewRecord
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('stock_card')->label('Stock card')->icon('heroicon-o-queue-list')->color('gray')
                ->visible(fn (Item $item): bool => $item->type === ItemType::Inventory && StockCardPage::canAccess())
                ->url(fn (Item $item): string => StockCardPage::getUrl(['item' => $item->id])),
            EditAction::make(),
        ];
    }
}
