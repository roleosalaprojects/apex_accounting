<?php

declare(strict_types=1);

namespace App\Filament\Resources\VendorPayments\Schemas;

use App\Enums\AccountSubtype;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Vendor;
use App\Models\WithholdingCode;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VendorPaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('vendor_id')->label('Vendor')
                ->options(fn () => Vendor::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()->required(),
            DatePicker::make('payment_date')->default(now())->required(),
            Select::make('method')->options(PaymentMethod::class)->default('check')->required(),
            Select::make('paid_from_account_id')->label('Paid from')
                ->options(fn () => Account::query()
                    ->whereIn('subtype', [AccountSubtype::Cash->value, AccountSubtype::Bank->value])
                    ->orderBy('code')->get()->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"]))
                ->required(),
            Select::make('withholding_code_id')->label('EWT code (optional)')
                ->options(fn () => WithholdingCode::query()->get()->mapWithKeys(fn (WithholdingCode $w) => [$w->id => "{$w->code} — {$w->name}"]))
                ->helperText('Defaults to the vendor\'s default code if left blank.'),
            TextInput::make('external_reference_no')->label('Check no.'),
            Repeater::make('applications')->label('Apply to bills')
                ->columnSpanFull()->minItems(1)->defaultItems(1)->columns(2)
                ->schema([
                    Select::make('bill_id')->label('Bill')
                        ->options(fn () => Bill::query()
                            ->whereIn('status', ['posted', 'partially_paid'])->get()
                            ->mapWithKeys(fn (Bill $b) => [$b->id => $b->number.' (bal '.number_format($b->outstanding() / 100, 2).')']))
                        ->searchable()->required(),
                    TextInput::make('amount')->label('Gross amount (P)')->numeric()->required(),
                ]),
        ]);
    }
}
