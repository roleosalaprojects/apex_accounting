<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaxReturns;

use App\Filament\Resources\TaxReturns\Pages\CreateTaxReturn;
use App\Filament\Resources\TaxReturns\Pages\ListTaxReturns;
use App\Filament\Resources\TaxReturns\Schemas\TaxReturnForm;
use App\Filament\Resources\TaxReturns\Tables\TaxReturnsTable;
use App\Models\TaxReturn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TaxReturnResource extends Resource
{
    protected static ?string $model = TaxReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Tax Returns';

    public static function form(Schema $schema): Schema
    {
        return TaxReturnForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxReturnsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxReturns::route('/'),
            'create' => CreateTaxReturn::route('/create'),
        ];
    }
}
