<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Enums\PricingMode;
use App\Enums\VatBucket;
use App\Models\Account;
use App\Models\Item;
use App\Models\TaxCode;
use App\Models\Vendor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('vendor_id')->label('Vendor')
                    ->options(fn (): array => Vendor::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->required(),
                DatePicker::make('order_date')->default(now())->required(),
                DatePicker::make('expected_date'),
                Select::make('pricing_mode')->options(PricingMode::class)
                    ->default(PricingMode::VatExclusive->value)->required(),
                Select::make('status')
                    ->options(['draft' => 'Draft', 'sent' => 'Sent', 'received' => 'Received', 'cancelled' => 'Cancelled'])
                    ->default('draft')->required()
                    ->disabled(fn (?string $state): bool => $state === 'billed'),
                TextInput::make('reference')->label("Vendor's reference")->maxLength(160),
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
                        Select::make('vat_bucket')->label('Input VAT bucket')
                            ->options(VatBucket::class)
                            ->helperText('Required when the line carries 12% input VAT.')
                            ->columnSpan(6),
                        Select::make('expense_account_id')->label('Expense / asset account')
                            ->options(fn (): array => Account::query()->orderBy('code')->get()
                                ->mapWithKeys(fn (Account $a): array => [$a->id => "{$a->code} — {$a->name}"])->all())
                            ->searchable()->required()->columnSpan(6),
                    ]),
            ]);
    }
}
