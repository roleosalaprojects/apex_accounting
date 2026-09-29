<?php

declare(strict_types=1);

namespace App\Filament\Resources\Departments\Schemas;

use App\Filament\Support\CreatedBySelect;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                CreatedBySelect::make(),
            ]);
    }
}
