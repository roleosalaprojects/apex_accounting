<?php

declare(strict_types=1);

namespace App\Filament\Resources\CreditMemos\Schemas;

use App\Enums\AccountType;
use App\Enums\PricingMode;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Item;
use App\Models\TaxCode;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Credit memo entry form. Submission is handled by CreateCreditMemo ->
 * PostCreditMemo (§6.1); the form collects header + lines only.
 */
class CreditMemoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->label('Customer')
                    ->options(fn () => Customer::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                DatePicker::make('memo_date')
                    ->default(now())
                    ->required(),
                Select::make('pricing_mode')
                    ->options(PricingMode::class)
                    ->default(PricingMode::VatInclusive->value)
                    ->required(),
                Textarea::make('memo')->label('Reason / memo')->columnSpanFull(),

                Repeater::make('lines')
                    ->label('Line items')
                    ->columnSpanFull()
                    ->minItems(1)
                    ->defaultItems(1)
                    ->columns(12)
                    ->schema([
                        Select::make('item_id')
                            ->label('Item')
                            ->options(fn () => Item::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->columnSpan(3),
                        TextInput::make('description')->required()->columnSpan(3),
                        TextInput::make('qty')->numeric()->default(1)->required()->columnSpan(2),
                        TextInput::make('unit_price')
                            ->label('Unit price (₱)')
                            ->numeric()->required()->columnSpan(2),
                        Select::make('tax_code_id')
                            ->label('Tax')
                            ->options(fn () => TaxCode::query()->pluck('code', 'id'))
                            ->required()->columnSpan(2),
                        Select::make('income_account_id')
                            ->label('Income account')
                            ->options(fn () => Account::query()
                                ->where('type', AccountType::Income->value)
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"]))
                            ->required()->columnSpan(12),
                    ]),
            ]);
    }
}
