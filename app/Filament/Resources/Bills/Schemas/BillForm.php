<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bills\Schemas;

use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->required(),
                Select::make('vendor_id')
                    ->relationship('vendor', 'name')
                    ->required(),
                TextInput::make('number'),
                DatePicker::make('bill_date')
                    ->required(),
                DatePicker::make('due_date'),
                Select::make('status')
                    ->options(InvoiceStatus::class)
                    ->default('draft')
                    ->required(),
                Select::make('pricing_mode')
                    ->options(PricingMode::class)
                    ->default('vat_exclusive')
                    ->required(),
                Toggle::make('is_opening')
                    ->required(),
                TextInput::make('vatable_purchases')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('input_vat')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('exempt_purchases')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('journal_entry_id')
                    ->relationship('journalEntry', 'id'),
                Textarea::make('memo')
                    ->columnSpanFull(),
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
                TextInput::make('department_id')
                    ->numeric(),
                TextInput::make('project_id')
                    ->numeric(),
                TextInput::make('fund_id')
                    ->numeric(),
                TextInput::make('branch_id')
                    ->numeric(),
            ]);
    }
}
