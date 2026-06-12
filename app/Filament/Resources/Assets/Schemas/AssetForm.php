<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets\Schemas;

use App\Models\AssetCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('asset_category_id')->label('Category')
                ->options(fn () => AssetCategory::query()->orderBy('name')->pluck('name', 'id'))
                ->live()
                ->afterStateUpdated(function ($state, callable $set): void {
                    $months = AssetCategory::query()->find($state)?->default_useful_life_months;
                    if ($months !== null) {
                        $set('useful_life_months', $months);
                    }
                })
                ->required(),
            TextInput::make('name')->required()->maxLength(160),
            TextInput::make('number')->label('Asset no.')->maxLength(60),
            DatePicker::make('acquisition_date')->default(now())->required(),
            TextInput::make('acquisition_cost')->label('Acquisition cost (P)')->numeric()->required(),
            TextInput::make('salvage_value')->label('Salvage value (P)')->numeric()->default(0)->required(),
            TextInput::make('useful_life_months')->label('Useful life (months)')->numeric()->integer()->required(),
        ]);
    }
}
