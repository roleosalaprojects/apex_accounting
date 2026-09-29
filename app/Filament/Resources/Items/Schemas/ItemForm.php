<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items\Schemas;

use App\Enums\AccountType;
use App\Enums\ItemType;
use App\Filament\Support\PesoInput;
use App\Models\Account;
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
                Select::make('income_account_id')
                    ->label('Income account')
                    ->options(fn (): array => self::accounts(AccountType::Income))
                    ->searchable(),
                Select::make('cogs_account_id')
                    ->label('Cost of sales account')
                    ->options(fn (): array => self::accounts(AccountType::Expense))
                    ->searchable(),
                Select::make('inventory_account_id')
                    ->label('Inventory account')
                    ->options(fn (): array => self::accounts(AccountType::Asset))
                    ->searchable(),
                PesoInput::make('default_sales_price')
                    ->required()
                    ->default(0),
                PesoInput::make('default_purchase_price')
                    ->required()
                    ->default(0),
                TextInput::make('unit')
                    ->required()
                    ->default('pc'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function accounts(AccountType $type): array
    {
        return Account::query()
            ->where('type', $type->value)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $account): array => [$account->id => "{$account->code} — {$account->name}"])
            ->all();
    }
}
