<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Schemas;

use App\Filament\Support\CreatedBySelect;
use App\Filament\Support\PesoInput;
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
                PesoInput::make('credit_limit'),
                CreatedBySelect::make(),
            ]);
    }
}
