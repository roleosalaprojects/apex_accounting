<?php

declare(strict_types=1);

namespace App\Filament\Resources\AssetCategories;

use App\Enums\AccountType;
use App\Filament\Resources\AssetCategories\Pages\CreateAssetCategory;
use App\Filament\Resources\AssetCategories\Pages\EditAssetCategory;
use App\Filament\Resources\AssetCategories\Pages\ListAssetCategories;
use App\Models\Account;
use App\Models\AssetCategory;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetCategoryResource extends Resource
{
    protected static ?string $model = AssetCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Fixed Assets';

    protected static ?string $navigationLabel = 'Asset Categories';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            Select::make('fixed_asset_account_id')->label('Fixed asset account')
                ->options(fn () => self::accountOptions(AccountType::Asset))->required(),
            Select::make('accum_depreciation_account_id')->label('Accumulated depreciation account')
                ->options(fn () => self::accountOptions(AccountType::Asset))->required(),
            Select::make('depreciation_expense_account_id')->label('Depreciation expense account')
                ->options(fn () => self::accountOptions(AccountType::Expense))->required(),
            TextInput::make('default_useful_life_months')->label('Default useful life (months)')
                ->numeric()->integer()->default(60)->required(),
            Select::make('method')->options(['straight_line' => 'Straight line'])->default('straight_line')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('fixedAssetAccount.code')->label('Asset acct'),
            TextColumn::make('default_useful_life_months')->label('Life (months)'),
            TextColumn::make('method'),
            TextColumn::make('assets_count')->counts('assets')->label('Assets'),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private static function accountOptions(AccountType $type): array
    {
        return Account::query()->where('type', $type->value)->orderBy('code')->get()
            ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"])->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetCategories::route('/'),
            'create' => CreateAssetCategory::route('/create'),
            'edit' => EditAssetCategory::route('/{record}/edit'),
        ];
    }
}
