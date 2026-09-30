<?php

declare(strict_types=1);

namespace App\Filament\Resources\DebitMemos\Schemas;

use App\Enums\PricingMode;
use App\Enums\VatBucket;
use App\Filament\Support\PurchaseLineDefaults;
use App\Models\Account;
use App\Models\Item;
use App\Models\TaxCode;
use App\Models\Vendor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Debit memo entry form: the vendor, the reason, and the returned or credited
 * lines with the same VAT bucket the bill used. CreateDebitMemo posts it.
 */
class DebitMemoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('vendor_id')
                    ->label('Vendor')
                    ->options(fn () => Vendor::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                DatePicker::make('memo_date')->default(now())->required(),
                Select::make('pricing_mode')
                    ->options(PricingMode::class)
                    ->default(PricingMode::VatExclusive->value)
                    ->required(),
                TextInput::make('external_reference_no')->label("Vendor's credit note no."),
                Textarea::make('memo')->label('Reason / memo')->columnSpanFull(),

                Repeater::make('lines')
                    ->label('Returned or credited lines')
                    ->columnSpanFull()
                    ->minItems(1)
                    ->defaultItems(1)
                    ->columns(12)
                    ->schema([
                        Select::make('item_id')
                            ->label('Item')
                            ->options(fn () => Item::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (mixed $state, Get $get, Set $set) => PurchaseLineDefaults::apply($state, $get, $set, 'expense_or_asset_account_id'))
                            ->columnSpan(3),
                        TextInput::make('description')->required()->columnSpan(3),
                        TextInput::make('qty')->numeric()->default(1)->required()->columnSpan(2),
                        TextInput::make('unit_price')->label('Unit price (₱)')->numeric()->required()->columnSpan(2),
                        Select::make('tax_code_id')
                            ->label('Tax')
                            ->options(fn () => TaxCode::query()->pluck('code', 'id'))
                            ->required()->columnSpan(2)->searchable(),
                        Select::make('vat_bucket')
                            ->label('Input VAT bucket')
                            ->options(VatBucket::class)
                            ->helperText('The bucket the original bill used; required when the line carries 12% VAT.')
                            ->columnSpan(6),
                        Select::make('expense_or_asset_account_id')
                            ->label('Expense / asset account')
                            ->options(fn () => Account::query()->orderBy('code')->get()
                                ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"]))
                            ->searchable()
                            ->disabled(fn (Get $get): bool => PurchaseLineDefaults::locksAccount($get('item_id')))
                            ->dehydrated()
                            ->helperText(fn (Get $get): ?string => PurchaseLineDefaults::locksAccount($get('item_id')) ? 'Stocked items leave through their inventory account.' : null)
                            ->required()->columnSpan(6),
                    ]),
            ]);
    }
}
