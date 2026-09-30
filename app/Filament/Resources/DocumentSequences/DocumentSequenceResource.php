<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentSequences;

use App\Filament\Resources\DocumentSequences\Pages\EditDocumentSequence;
use App\Filament\Resources\DocumentSequences\Pages\ListDocumentSequences;
use App\Models\DocumentSequence;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The number series behind every document (INV-2026-000485 …): the prefix,
 * the next number and the zero-padding, per company. Series are created with
 * the company; here they are only adjusted, and the next number can only be
 * moved forward so a number is never issued twice.
 */
class DocumentSequenceResource extends Resource
{
    protected static ?string $model = DocumentSequence::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Document Numbers';

    protected static ?string $modelLabel = 'document number series';

    protected static ?string $recordTitleAttribute = 'key';

    private const LABELS = [
        'journal_entry' => 'Journal entries',
        'invoice' => 'Sales invoices',
        'credit_memo' => 'Credit memos',
        'payment_in' => 'Customer payments',
        'collection_receipt' => 'Collection receipts',
        'bill' => 'Bills',
        'debit_memo' => 'Debit memos',
        'payment_out' => 'Vendor payments',
        'payment_voucher' => 'Payment vouchers',
        'purchase_order' => 'Purchase orders',
        'sales_order' => 'Sales orders',
        'delivery' => 'Delivery receipts',
        'asset' => 'Fixed assets',
        'recurring_run' => 'Recurring runs',
    ];

    public static function label(string $key): string
    {
        return self::LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /** What the series will issue next, e.g. INV-2026-000485. */
    public static function preview(DocumentSequence $sequence): string
    {
        $prefix = $sequence->prefix !== '' ? $sequence->prefix.'-' : '';

        return $prefix.now()->year.'-'.str_pad((string) $sequence->next_number, $sequence->padding, '0', STR_PAD_LEFT);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->label('Document')
                ->formatStateUsing(fn (?string $state): string => self::label((string) $state))
                ->disabled()->dehydrated(false),
            TextInput::make('prefix')
                ->maxLength(10)
                ->regex('/^[A-Z0-9]*$/')
                ->helperText('Capital letters and digits; the year and number follow it, as in INV-2026-000485.'),
            TextInput::make('next_number')->label('Next number')
                ->numeric()->integer()->required()
                ->minValue(fn (?DocumentSequence $record): int => $record?->next_number ?? 1)
                ->helperText('Can only move forward, so a number is never issued twice.'),
            TextInput::make('padding')->label('Digits')
                ->numeric()->integer()->required()->minValue(4)->maxValue(8),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->label('Document')
                    ->formatStateUsing(fn (string $state): string => self::label($state))
                    ->sortable(),
                TextColumn::make('prefix'),
                TextColumn::make('next_number')->label('Next number')->alignEnd(),
                TextColumn::make('padding')->label('Digits')->alignEnd(),
                TextColumn::make('preview')->label('Next issued')
                    ->state(fn (DocumentSequence $record): string => self::preview($record))
                    ->fontFamily('mono'),
            ])
            ->defaultSort('key')
            ->paginated(false)
            ->recordActions([EditAction::make()]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentSequences::route('/'),
            'edit' => EditDocumentSequence::route('/{record}/edit'),
        ];
    }
}
