<?php

declare(strict_types=1);

namespace App\Filament\Resources\Vendors\Schemas;

use App\Filament\Support\CreatedBySelect;
use App\Models\WithholdingCode;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('tin'),
                TextInput::make('email')->email()->maxLength(160)
                    ->helperText('Where purchase orders are sent.'),
                TextInput::make('contact_person')->label('Contact person')->maxLength(120),
                Textarea::make('address')
                    ->columnSpanFull(),
                Toggle::make('is_vat_registered')
                    ->required(),
                Select::make('default_withholding_code_id')
                    ->relationship('defaultWithholdingCode', 'name')
                    ->getOptionLabelFromRecordUsing(fn (WithholdingCode $record): string => "{$record->atc} — {$record->name}")
                    ->searchable(['atc', 'name'])
                    ->preload(),
                TextInput::make('terms_days')
                    ->required()
                    ->numeric()
                    ->default(0),
                CreatedBySelect::make(),
            ]);
    }
}
