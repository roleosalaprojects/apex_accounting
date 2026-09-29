<?php

declare(strict_types=1);

namespace App\Filament\Resources\PosZReadings;

use App\Enums\PosZReadingStatus;
use App\Filament\Resources\PosZReadings\Pages\ListPosZReadings;
use App\Filament\Resources\PosZReadings\Tables\PosZReadingsTable;
use App\Models\PosZReading;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PosZReadingResource extends Resource
{
    protected static ?string $model = PosZReading::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Integrations';

    protected static ?string $navigationLabel = 'POS Z-Readings';

    public static function table(Table $table): Table
    {
        return PosZReadingsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = PosZReading::query()->where('status', PosZReadingStatus::Pending->value)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'POS Z-readings awaiting import';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosZReadings::route('/'),
        ];
    }
}
