<?php

declare(strict_types=1);

namespace App\Filament\Resources\DebitMemos;

use App\Filament\Resources\DebitMemos\Pages\CreateDebitMemo;
use App\Filament\Resources\DebitMemos\Pages\ListDebitMemos;
use App\Filament\Resources\DebitMemos\Pages\ViewDebitMemo;
use App\Filament\Resources\DebitMemos\Schemas\DebitMemoForm;
use App\Filament\Resources\DebitMemos\Schemas\DebitMemoInfolist;
use App\Filament\Resources\DebitMemos\Tables\DebitMemosTable;
use App\Models\DebitMemo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Vendor debit memos — goods returned or credits granted — post through
 * PostDebitMemo and settle bills via ApplyDebitMemo; immutable once posted.
 */
class DebitMemoResource extends Resource
{
    protected static ?string $model = DebitMemo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?string $modelLabel = 'debit memo';

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return DebitMemoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DebitMemoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DebitMemosTable::configure($table);
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
            'index' => ListDebitMemos::route('/'),
            'create' => CreateDebitMemo::route('/create'),
            'view' => ViewDebitMemo::route('/{record}'),
        ];
    }
}
