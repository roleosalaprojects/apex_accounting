<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankAccounts\Tables;

use App\Models\BankAccount;
use App\Services\Banking\BankBalanceService;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BankAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('account.code')->label('GL code')->sortable(),
                TextColumn::make('account.name')->label('GL account')->searchable(),
                TextColumn::make('bank_name')->searchable(),
                TextColumn::make('account_no')->label('Account no.'),
                TextColumn::make('balance')->label('Book balance')
                    ->state(fn (BankAccount $record): string => '₱'.number_format(
                        app(BankBalanceService::class)->currentBalance($record) / 100, 2
                    )),
                IconColumn::make('is_active')->boolean(),
            ]);
    }
}
