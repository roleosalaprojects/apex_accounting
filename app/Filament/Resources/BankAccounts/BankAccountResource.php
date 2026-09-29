<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankAccounts;

use App\Filament\Resources\BankAccounts\Pages\CreateBankAccount;
use App\Filament\Resources\BankAccounts\Pages\EditBankAccount;
use App\Filament\Resources\BankAccounts\Pages\ListBankAccounts;
use App\Filament\Resources\BankAccounts\Pages\ViewBankAccount;
use App\Filament\Resources\BankAccounts\Schemas\BankAccountForm;
use App\Filament\Resources\BankAccounts\Schemas\BankAccountInfolist;
use App\Filament\Resources\BankAccounts\Tables\BankAccountsTable;
use App\Filament\Support\Widgets\LedgerActivity;
use App\Models\BankAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = 'Banking';

    public static function form(Schema $schema): Schema
    {
        return BankAccountForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BankAccountInfolist::configure($schema);
    }

    public static function hasRecordTitle(): bool
    {
        return true;
    }

    /** "BDO Unibank 0045-1234-5678" rather than the model label. */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if (! $record instanceof BankAccount) {
            return parent::getRecordTitle($record);
        }

        return trim(($record->bank_name ?? '').' '.($record->account_no ?? '')) ?: parent::getRecordTitle($record);
    }

    public static function table(Table $table): Table
    {
        return BankAccountsTable::configure($table);
    }

    public static function getWidgets(): array
    {
        return [LedgerActivity::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankAccounts::route('/'),
            'create' => CreateBankAccount::route('/create'),
            'view' => ViewBankAccount::route('/{record}'),
            'edit' => EditBankAccount::route('/{record}/edit'),
        ];
    }
}
