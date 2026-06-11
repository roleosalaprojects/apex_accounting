<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalEntries\Schemas;

use App\Enums\JournalStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class JournalEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->required(),
                Select::make('period_id')
                    ->relationship('period', 'id')
                    ->required(),
                TextInput::make('number'),
                DatePicker::make('entry_date')
                    ->required(),
                Textarea::make('memo')
                    ->columnSpanFull(),
                TextInput::make('source_type'),
                TextInput::make('source_id')
                    ->numeric(),
                Select::make('status')
                    ->options(JournalStatus::class)
                    ->default('draft')
                    ->required(),
                Select::make('reversal_of_id')
                    ->relationship('reversalOf', 'id'),
                TextInput::make('reversed_by_id')
                    ->numeric(),
                TextInput::make('reversal_reason'),
                TextInput::make('reference_no'),
                TextInput::make('external_reference_no'),
                Textarea::make('remarks')
                    ->columnSpanFull(),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('checked_by')
                    ->numeric(),
                DateTimePicker::make('checked_at'),
                TextInput::make('approved_by')
                    ->numeric(),
                DateTimePicker::make('approved_at'),
                TextInput::make('posted_by')
                    ->numeric(),
                DateTimePicker::make('posted_at'),
                TextInput::make('total_debits')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_credits')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
