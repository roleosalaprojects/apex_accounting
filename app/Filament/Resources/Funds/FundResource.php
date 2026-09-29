<?php

declare(strict_types=1);

namespace App\Filament\Resources\Funds;

use App\Filament\Resources\Funds\Pages\CreateFund;
use App\Filament\Resources\Funds\Pages\EditFund;
use App\Filament\Resources\Funds\Pages\ListFunds;
use App\Filament\Resources\Funds\Pages\ViewFund;
use App\Filament\Resources\Funds\Schemas\FundForm;
use App\Filament\Resources\Funds\Tables\FundsTable;
use App\Filament\Support\DimensionInfolist;
use App\Filament\Support\Widgets\LedgerActivity;
use App\Models\Fund;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FundResource extends Resource
{
    protected static ?string $model = Fund::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return FundForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DimensionInfolist::configure($schema, 'fund');
    }

    public static function table(Table $table): Table
    {
        return FundsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getWidgets(): array
    {
        return [LedgerActivity::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFunds::route('/'),
            'create' => CreateFund::route('/create'),
            'view' => ViewFund::route('/{record}'),
            'edit' => EditFund::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
