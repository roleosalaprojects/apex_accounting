<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankAccounts\Schemas;

use App\Enums\AccountSubtype;
use App\Models\Account;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BankAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('account_id')->label('GL account')
                ->options(fn () => Account::query()
                    ->whereIn('subtype', [AccountSubtype::Cash->value, AccountSubtype::Bank->value])
                    ->orderBy('code')->get()->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"]))
                ->required(),
            TextInput::make('bank_name')->maxLength(120),
            TextInput::make('account_no')->label('Account no.')->maxLength(60),
            Toggle::make('is_active')->default(true),
        ]);
    }
}
