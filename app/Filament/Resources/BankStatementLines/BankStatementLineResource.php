<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankStatementLines;

use App\Filament\Resources\BankStatementLines\Pages\ListBankStatementLines;
use App\Filament\Resources\BankStatementLines\Tables\BankStatementLinesTable;
use App\Models\BankStatementLine;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BankStatementLineResource extends Resource
{
    protected static ?string $model = BankStatementLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Banking';

    protected static ?string $navigationLabel = 'Bank Statements';

    public static function table(Table $table): Table
    {
        return BankStatementLinesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankStatementLines::route('/'),
        ];
    }
}
