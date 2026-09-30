<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Support\Peso;
use App\Filament\Support\ShownOnViewPage;
use App\Models\DepreciationEntry;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** The monthly depreciation posted so far, each opening its journal entry. */
class DepreciationRelationManager extends RelationManager
{
    use ShownOnViewPage;

    protected static string $relationship = 'depreciationEntries';

    protected static ?string $title = 'Depreciation posted';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['period', 'journalEntry']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('period.starts_on')->label('Month')->date('M Y'),
                TextColumn::make('amount')->alignEnd()
                    ->formatStateUsing(fn (Money $state): string => Peso::format($state)),
                TextColumn::make('journalEntry.number')->label('Journal entry')->placeholder('—'),
            ])
            ->recordUrl(fn (DepreciationEntry $record): ?string => $record->journal_entry_id === null
                ? null
                : JournalEntryResource::getUrl('view', ['record' => $record->journal_entry_id]))
            ->emptyStateHeading('No depreciation posted yet')
            ->emptyStateDescription('Monthly depreciation runs once the asset is in service.');
    }
}
