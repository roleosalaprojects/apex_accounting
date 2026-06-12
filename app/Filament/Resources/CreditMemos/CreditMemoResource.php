<?php

declare(strict_types=1);

namespace App\Filament\Resources\CreditMemos;

use App\Filament\Resources\CreditMemos\Pages\CreateCreditMemo;
use App\Filament\Resources\CreditMemos\Pages\ListCreditMemos;
use App\Filament\Resources\CreditMemos\Schemas\CreditMemoForm;
use App\Filament\Resources\CreditMemos\Tables\CreditMemosTable;
use App\Models\CreditMemo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Credit memos post through PostCreditMemo (§6.1) and are applied to open
 * invoices via ApplyCreditMemo — both immutable once posted.
 */
class CreditMemoResource extends Resource
{
    protected static ?string $model = CreditMemo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    public static function form(Schema $schema): Schema
    {
        return CreditMemoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CreditMemosTable::configure($table);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditMemos::route('/'),
            'create' => CreateCreditMemo::route('/create'),
        ];
    }
}
