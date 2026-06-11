<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->required(),
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('tin'),
                Textarea::make('address')
                    ->columnSpanFull(),
                Toggle::make('is_withholding_agent')
                    ->required(),
                TextInput::make('terms_days')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('credit_limit')
                    ->numeric(),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
