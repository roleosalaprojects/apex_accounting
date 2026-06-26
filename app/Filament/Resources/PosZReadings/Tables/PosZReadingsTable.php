<?php

declare(strict_types=1);

namespace App\Filament\Resources\PosZReadings\Tables;

use App\Actions\Integration\ImportPosZReading;
use App\Enums\PosZReadingStatus;
use App\Models\PosZReading;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Throwable;

class PosZReadingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business_date')->label('Date')->date()->sortable(),
                TextColumn::make('reference')->label('Z-reading')->placeholder('—')->searchable(),
                TextColumn::make('total_sales')->label('Sales')->alignEnd()
                    ->state(fn (PosZReading $r): string => number_format($r->totalSales() / 100, 2)),
                TextColumn::make('vat_amount')->label('Output VAT')->alignEnd()
                    ->state(fn (PosZReading $r): string => number_format($r->vat_amount / 100, 2))
                    ->toggleable(),
                TextColumn::make('discounts')->alignEnd()
                    ->state(fn (PosZReading $r): string => number_format($r->discounts / 100, 2))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge()
                    ->color(fn (PosZReadingStatus $state): string => match ($state) {
                        PosZReadingStatus::Imported => 'success',
                        PosZReadingStatus::Dismissed => 'gray',
                        PosZReadingStatus::Pending => 'warning',
                    }),
                TextColumn::make('journalEntry.id')->label('Draft entry')
                    ->prefix('#')->placeholder('—')->toggleable(),
                TextColumn::make('imported_at')->dateTime()->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('business_date', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    PosZReadingStatus::Pending->value => 'Pending',
                    PosZReadingStatus::Imported->value => 'Imported',
                    PosZReadingStatus::Dismissed->value => 'Dismissed',
                ]),
            ])
            ->recordActions([
                Action::make('import')
                    ->label('Import as draft')->icon('heroicon-o-arrow-right-circle')->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Create a draft journal entry from this Z-reading. Review and post it from Journal Entries.')
                    ->visible(fn (PosZReading $r): bool => $r->isPending())
                    ->action(function (PosZReading $record): void {
                        try {
                            $draft = app(ImportPosZReading::class)->handle($record, Auth::user());
                            Notification::make()->success()
                                ->title('Imported as draft journal entry')
                                ->body("Draft entry #{$draft->id} is ready for review in Journal Entries.")
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Could not import')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('dismiss')
                    ->label('Dismiss')->icon('heroicon-o-eye-slash')->color('gray')
                    ->visible(fn (PosZReading $r): bool => $r->isPending())
                    ->action(fn (PosZReading $record) => $record->update(['status' => PosZReadingStatus::Dismissed])),
                Action::make('restore')
                    ->label('Restore')->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->visible(fn (PosZReading $r): bool => $r->status === PosZReadingStatus::Dismissed)
                    ->action(fn (PosZReading $record) => $record->update(['status' => PosZReadingStatus::Pending])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('importSelected')
                        ->label('Import selected as drafts')->icon('heroicon-o-arrow-right-circle')->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $importer = app(ImportPosZReading::class);
                            $imported = 0;
                            $failed = 0;
                            foreach ($records as $record) {
                                if (! $record instanceof PosZReading || ! $record->isPending()) {
                                    continue;
                                }
                                try {
                                    $importer->handle($record, Auth::user());
                                    $imported++;
                                } catch (Throwable) {
                                    $failed++;
                                }
                            }
                            Notification::make()
                                ->title("Imported {$imported} Z-reading(s) as drafts")
                                ->body($failed > 0 ? "{$failed} could not be imported (e.g. a closed period)." : null)
                                ->status($failed > 0 ? 'warning' : 'success')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
