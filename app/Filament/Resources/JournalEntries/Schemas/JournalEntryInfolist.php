<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalEntries\Schemas;

use App\Filament\Support\AttachmentsSection;
use App\Filament\Support\Peso;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JournalEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Journal Entry')->columns(4)->schema([
                TextEntry::make('number'),
                TextEntry::make('entry_date')->date(),
                TextEntry::make('status')->badge(),
                TextEntry::make('memo')->placeholder('—'),
                TextEntry::make('source_type')->label('Source')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : 'Manual')
                    ->placeholder('Manual'),
                TextEntry::make('reversal_reason')->label('Reversal reason')->placeholder('—'),
                TextEntry::make('reversalOf.number')->label('Reverses')->placeholder('—'),
                TextEntry::make('reversedBy.number')->label('Reversed by')->placeholder('—'),
            ]),
            Section::make('Lines')->schema([
                RepeatableEntry::make('lines')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('account.code')->label('Code'),
                    TextEntry::make('account.name')->label('Account'),
                    TextEntry::make('debit')->formatStateUsing(fn ($state) => Peso::format($state)),
                    TextEntry::make('credit')->formatStateUsing(fn ($state) => Peso::format($state)),
                ]),
            ]),
            Section::make('Totals')->columns(2)->schema([
                TextEntry::make('total_debits')->label('Total debits')->formatStateUsing(fn ($state) => Peso::format($state)),
                TextEntry::make('total_credits')->label('Total credits')->formatStateUsing(fn ($state) => Peso::format($state)),
            ]),
            AttachmentsSection::make(),
        ]);
    }
}
