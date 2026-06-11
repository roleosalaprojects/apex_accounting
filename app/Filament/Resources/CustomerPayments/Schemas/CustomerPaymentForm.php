<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerPayments\Schemas;

use App\Enums\AccountSubtype;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Invoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerPaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('customer_id')->label('Customer')
                ->options(fn () => Customer::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()->required(),
            DatePicker::make('payment_date')->default(now())->required(),
            Select::make('method')->options(PaymentMethod::class)->default('bank')->required(),
            Select::make('deposit_to_account_id')->label('Deposit to')
                ->options(fn () => Account::query()
                    ->whereIn('subtype', [AccountSubtype::Cash->value, AccountSubtype::Bank->value])
                    ->orderBy('code')->get()->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"]))
                ->required(),
            TextInput::make('amount')->label('Cash received (P)')->numeric()->required(),
            TextInput::make('ewt_withheld')->label('EWT withheld (P)')->numeric()->default(0)
                ->helperText('When the customer is a withholding agent (2307).'),
            Repeater::make('applications')->label('Apply to invoices')
                ->columnSpanFull()->minItems(1)->defaultItems(1)->columns(2)
                ->schema([
                    Select::make('invoice_id')->label('Invoice')
                        ->options(fn () => Invoice::query()
                            ->whereIn('status', ['posted', 'partially_paid'])->get()
                            ->mapWithKeys(fn (Invoice $i) => [$i->id => $i->number.' (bal '.number_format($i->outstanding() / 100, 2).')']))
                        ->searchable()->required(),
                    TextInput::make('amount')->label('Amount (P)')->numeric()->required(),
                ]),
        ]);
    }
}
