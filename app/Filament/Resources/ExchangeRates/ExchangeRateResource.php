<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExchangeRates;

use App\Filament\Resources\ExchangeRates\Pages\CreateExchangeRate;
use App\Filament\Resources\ExchangeRates\Pages\EditExchangeRate;
use App\Filament\Resources\ExchangeRates\Pages\ListExchangeRates;
use App\Models\ExchangeRate;
use App\Support\Currencies;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ExchangeRateResource extends Resource
{
    protected static ?string $model = ExchangeRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Exchange Rates';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('currency_code')->label('Currency')
                ->options(Currencies::foreignOptions())->required(),
            DatePicker::make('rate_date')->default(now())->required(),
            TextInput::make('rate')->label('PHP per 1 unit')
                ->numeric()->minValue(0)->required()
                ->helperText('e.g. 56.00 means 1 USD = ₱56.00'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('currency_code')->label('Currency')->badge()->sortable(),
                TextColumn::make('rate_date')->date()->sortable(),
                TextColumn::make('rate')->numeric(decimalPlaces: 4)->sortable(),
            ])
            ->defaultSort('rate_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExchangeRates::route('/'),
            'create' => CreateExchangeRate::route('/create'),
            'edit' => EditExchangeRate::route('/{record}/edit'),
        ];
    }
}
