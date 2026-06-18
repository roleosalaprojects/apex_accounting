<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Schemas;

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

class SalesOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')->label('Customer')
                    ->options(fn (): array => Customer::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->required(),
                DatePicker::make('order_date')->default(now())->required(),
                DatePicker::make('expiry_date'),
                Select::make('pricing_mode')->options(PricingMode::class)
                    ->default(PricingMode::VatInclusive->value)->required(),
                Select::make('status')
                    ->options(['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'cancelled' => 'Cancelled'])
                    ->default('draft')->required()
                    ->disabled(fn (?string $state): bool => $state === 'invoiced'),
                TextInput::make('reference')->maxLength(160),
                Textarea::make('notes')->columnSpanFull(),

                Repeater::make('lines')
                    ->relationship()
                    ->label('Line items')
                    ->columnSpanFull()
                    ->minItems(1)->defaultItems(1)->columns(12)
                    ->schema([
                        Select::make('item_id')->label('Item')
                            ->options(fn (): array => Item::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()->columnSpan(3),
                        TextInput::make('description')->required()->columnSpan(3),
                        TextInput::make('qty')->numeric()->default(1)->required()->columnSpan(2),
                        TextInput::make('unit_price')->label('Unit price (₱)')
                            ->numeric()->required()->columnSpan(2)
                            ->formatStateUsing(fn (?int $state): ?float => $state !== null ? $state / 100 : null)
                            ->dehydrateStateUsing(fn (mixed $state): int => (int) round((float) $state * 100)),
                        Select::make('tax_code_id')->label('Tax')
                            ->options(fn (): array => TaxCode::query()->pluck('code', 'id')->all())
                            ->required()->columnSpan(2),
                        Select::make('income_account_id')->label('Income account')
                            ->options(fn (): array => Account::query()->where('type', AccountType::Income->value)
                                ->orderBy('code')->get()->mapWithKeys(fn (Account $a): array => [$a->id => "{$a->code} — {$a->name}"])->all())
                            ->required()->columnSpan(12),
                    ]),
            ]);
    }
}
