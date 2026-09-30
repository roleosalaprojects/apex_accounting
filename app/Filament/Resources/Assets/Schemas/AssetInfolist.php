<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets\Schemas;

use App\Filament\Support\DimensionSelects;
use App\Filament\Support\KeyFigure;
use App\Filament\Support\Peso;
use App\Models\Asset;
use App\Services\Assets\AssetFigures;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $figures = fn (Asset $asset): array => once(fn (): array => app(AssetFigures::class)->build($asset));

        return $schema->columns(1)->components([
            Grid::make(['default' => 2, 'lg' => 4])->schema([
                KeyFigure::make('net_book_value', 'Net book value')
                    ->state(fn (Asset $asset): string => Peso::format($figures($asset)['net_book_value'])),
                KeyFigure::make('accumulated', 'Accumulated depreciation')
                    ->state(fn (Asset $asset): string => Peso::format($figures($asset)['accumulated'])),
                KeyFigure::make('monthly', 'Monthly depreciation')
                    ->state(fn (Asset $asset): string => Peso::format($figures($asset)['monthly'])),
                KeyFigure::make('progress', 'Months depreciated')
                    ->state(fn (Asset $asset): string => $figures($asset)['months_done'].' of '.$asset->useful_life_months
                        .($figures($asset)['last_month'] !== null ? ' · done '.$figures($asset)['last_month'] : '')),
            ]),
            Section::make('Asset')->columns(4)->schema([
                TextEntry::make('number')->label('No.')->placeholder('—'),
                TextEntry::make('name'),
                TextEntry::make('category.name')->label('Category'),
                TextEntry::make('status')->badge(),
                TextEntry::make('acquisition_date')->label('Acquired')->date(),
                TextEntry::make('in_service_date')->label('In service')->date()->placeholder('—'),
                TextEntry::make('acquisition_cost')->label('Cost')->formatStateUsing(fn ($state) => Peso::format($state)),
                TextEntry::make('salvage_value')->label('Salvage value')->formatStateUsing(fn ($state) => Peso::format($state)),
                TextEntry::make('useful_life_months')->label('Useful life')->formatStateUsing(fn (int $state): string => "{$state} months"),
                TextEntry::make('category.method')->label('Method')->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),
                TextEntry::make('disposed_at')->label('Disposed')->date()->placeholder('—'),
                TextEntry::make('disposal_proceeds')->label('Disposal proceeds')->placeholder('—')
                    ->formatStateUsing(fn (?int $state) => $state === null ? null : Peso::format($state)),
                TextEntry::make('disposal_gain_loss')->label('Gain / (loss) on disposal')->placeholder('—')
                    ->formatStateUsing(fn (?int $state) => $state === null ? null : ($state < 0 ? '('.Peso::format(-$state).')' : Peso::format($state))),
                TextEntry::make('description')->placeholder('—')->columnSpanFull(),
            ]),
            DimensionSelects::infolistSection(),
        ]);
    }
}
