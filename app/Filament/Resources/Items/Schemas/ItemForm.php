<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items\Schemas;

use App\Enums\ItemType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->required(),
                TextInput::make('sku')
                    ->label('SKU')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Select::make('type')
                    ->options(ItemType::class)
                    ->default('inventory')
                    ->required(),
                Toggle::make('is_vat_exempt_item')
                    ->required(),
                TextInput::make('income_account_id')
                    ->numeric(),
                TextInput::make('cogs_account_id')
                    ->numeric(),
                TextInput::make('inventory_account_id')
                    ->numeric(),
                TextInput::make('default_sales_price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('default_purchase_price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('unit')
                    ->required()
                    ->default('pc'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
