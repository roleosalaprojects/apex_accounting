<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Searchable "Created by" picker for master-data forms (models using
 * Models\Concerns\HasCreator). Lists only members of the current company —
 * never every user in the system — and defaults to the signed-in user.
 */
final class CreatedBySelect
{
    public static function make(): Select
    {
        return Select::make('created_by')
            ->label('Created by')
            ->relationship(
                name: 'createdBy',
                titleAttribute: 'name',
                modifyQueryUsing: fn (Builder $query): Builder => $query->whereHas(
                    'companies',
                    fn (Builder $companies): Builder => $companies->whereKey(Filament::getTenant()?->getKey()),
                ),
            )
            ->searchable(['name', 'email'])
            ->preload()
            ->default(fn (): int|string|null => Auth::id());
    }
}
