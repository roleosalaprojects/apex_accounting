<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaxReturns\Schemas;

use App\Enums\TaxReturnType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TaxReturnForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(fn (): array => collect(TaxReturnType::cases())
                        ->mapWithKeys(fn (TaxReturnType $t): array => [$t->value => $t->label()])->all())
                    ->default(TaxReturnType::Vat2550Q->value)
                    ->required(),
                TextInput::make('fiscal_year')
                    ->numeric()->integer()->minValue(2000)->maxValue(2100)
                    ->default((int) date('Y'))
                    ->required(),
                Select::make('quarter')
                    ->options([1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4'])
                    ->default(1)
                    ->required(),
            ]);
    }
}
