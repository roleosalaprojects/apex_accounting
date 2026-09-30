<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\PaymentMethod;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Support\DocumentStatus;
use App\Filament\Support\Peso;
use App\Filament\Support\PrintAction;
use App\Filament\Support\ShownOnViewPage;
use App\Models\CustomerPayment;
use App\Models\PaymentApplication;
use App\Services\Printing\PrintCollectionReceipt;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Payments received from the customer and the invoices each one settled. */
class PaymentsRelationManager extends RelationManager
{
    use ShownOnViewPage;

    protected static string $relationship = 'payments';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('applications.invoice'))
            ->defaultSort('payment_date', 'desc')
            ->columns([
                TextColumn::make('number')->searchable(),
                TextColumn::make('payment_date')->label('Date')->date('M j, Y')->sortable(),
                TextColumn::make('method')
                    ->formatStateUsing(fn (PaymentMethod $state): string => DocumentStatus::method($state)),
                TextColumn::make('applied_to')->label('Applied to')->wrap()
                    ->state(fn (CustomerPayment $record): string => $record->applications
                        ->map(fn (PaymentApplication $application): ?string => $application->invoice?->number)
                        ->filter()->join(', '))
                    ->placeholder('—'),
                TextColumn::make('amount')->label('Received')->alignEnd()->sortable()
                    ->formatStateUsing(fn (Money $state): string => Peso::format($state)),
                TextColumn::make('ewt_withheld')->label('EWT')->alignEnd()
                    ->formatStateUsing(fn (Money $state): string => $state->isZero() ? '—' : Peso::format($state)),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => $state === 'posted' ? 'success' : 'gray'),
            ])
            ->recordActions([
                PrintAction::make('receipt', 'Receipt',
                    fn (CustomerPayment $payment): string => app(PrintCollectionReceipt::class)->render($payment),
                    fn (CustomerPayment $payment): string => ($payment->collection_receipt_no ?? $payment->number).'.pdf')
                    ->visible(fn (CustomerPayment $payment): bool => $payment->status === 'posted'),
            ])
            ->recordUrl(fn (CustomerPayment $record): ?string => $record->journal_entry_id === null
                ? null
                : JournalEntryResource::getUrl('view', ['record' => $record->journal_entry_id]));
    }
}
