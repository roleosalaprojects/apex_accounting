<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\JournalStatus;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Support\Peso;
use App\Models\JournalEntry;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** The last entries to hit the ledger, newest first. */
class RecentJournalEntries extends TableWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = ['md' => 2];

    protected static ?string $heading = 'Recently posted';

    public static function canView(): bool
    {
        return JournalEntryResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => JournalEntry::query()
                ->whereIn('status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
                ->orderByDesc('posted_at')
                ->orderByDesc('id')
                ->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('entry_date')->label('Date')->date('M j, Y'),
                TextColumn::make('number')->label('Entry'),
                TextColumn::make('source_type')->label('Source')->badge()->color('gray')
                    ->default('manual')
                    ->formatStateUsing(fn (string $state): string => self::source($state))
                    ->visibleFrom('md'),
                TextColumn::make('memo')->limit(60)->wrap()->placeholder('—')->visibleFrom('md'),
                TextColumn::make('total_debits')->label('Amount')->alignEnd()
                    ->formatStateUsing(fn (Money $state): string => Peso::format($state)),
            ])
            ->recordUrl(fn (JournalEntry $record): string => JournalEntryResource::getUrl('view', ['record' => $record]));
    }

    private static function source(string $type): string
    {
        return match ($type) {
            'manual' => 'Manual',
            'pos.zreading' => 'POS',
            'hrms.payroll' => 'Payroll',
            default => str(class_basename($type))->headline()->toString(),
        };
    }
}
