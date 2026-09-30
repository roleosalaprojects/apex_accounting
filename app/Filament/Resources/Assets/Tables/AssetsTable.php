<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets\Tables;

use App\Filament\Resources\Assets\Actions\AssetActions;
use App\Filament\Support\Peso;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('No.')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('category.name')->label('Category'),
                TextColumn::make('acquisition_date')->date()->sortable(),
                TextColumn::make('acquisition_cost')->label('Cost')->formatStateUsing(fn (mixed $state): ?string => Peso::state($state))->alignEnd(),
                TextColumn::make('useful_life_months')->label('Life (mo)'),
                TextColumn::make('status')->badge(),
                TextColumn::make('in_service_date')->date()->label('In service'),
            ])
            ->recordActions([
                ViewAction::make(),
                AssetActions::placeInService(),
                AssetActions::dispose(),
            ])
            ->defaultSort('acquisition_date', 'desc');
    }
}
