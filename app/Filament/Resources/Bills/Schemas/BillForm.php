<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bills\Schemas;

use App\Enums\PricingMode;
use App\Enums\VatBucket;
use App\Models\Account;
use App\Models\Item;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Services\Fx\ExchangeRateService;
use App\Support\Currencies;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Bill entry form. Submission is handled by CreateBill -> PostBill (§5.3, §7);
 * the form collects header + lines (incl. the input-VAT bucket).
 */
class BillForm
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
                DatePicker::make('bill_date')->default(now())->required(),
                DatePicker::make('due_date'),
                Select::make('pricing_mode')
                    ->options(PricingMode::class)
                    ->default(PricingMode::VatExclusive->value)
                    ->required(),
                Select::make('currency_code')->label('Currency')
                    ->options(Currencies::options())->default(Currencies::FUNCTIONAL)
                    ->live()
                    ->afterStateUpdated(function ($state, $set, $get): void {
                        if ($state === Currencies::FUNCTIONAL) {
                            $set('exchange_rate', 1);

                            return;
                        }
                        try {
                            $set('exchange_rate', app(ExchangeRateService::class)
                                ->rateFor(Filament::getTenant()->id, $state, $get('bill_date') ?? now()->toDateString()));
                        } catch (\Throwable) {
                            // no stored rate — leave for manual entry
                        }
                    })
                    ->required(),
                TextInput::make('exchange_rate')->label('Exchange rate (PHP per 1 unit)')
                    ->numeric()->minValue(0)->default(1)->required(),
                TextInput::make('external_reference_no')->label("Vendor's invoice no."),
                Textarea::make('memo')->columnSpanFull(),

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
                        TextInput::make('unit_price')->label('Unit price (₱)')->numeric()->required()->columnSpan(2),
                        Select::make('tax_code_id')
                            ->label('Tax')
                            ->options(fn () => TaxCode::query()->pluck('code', 'id'))
                            ->required()->columnSpan(2),
                        Select::make('vat_bucket')
                            ->label('Input VAT bucket')
                            ->options(VatBucket::class)
                            ->helperText('Required when the line carries 12% input VAT.')
                            ->columnSpan(6),
                        Select::make('expense_or_asset_account_id')
                            ->label('Expense / asset account')
                            ->options(fn () => Account::query()->orderBy('code')->get()
                                ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"]))
                            ->searchable()
                            ->required()->columnSpan(6),
                    ]),
            ]);
    }
}
