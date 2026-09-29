<?php

declare(strict_types=1);

namespace App\Filament\Resources\Vendors\RelationManagers;

use App\Enums\PaymentMethod;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Support\DocumentStatus;
use App\Filament\Support\Peso;
use App\Filament\Support\ShownOnViewPage;
use App\Models\BillApplication;
use App\Models\VendorPayment;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Payments made to the vendor, the bills each one settled and the EWT withheld. */
class PaymentsRelationManager extends RelationManager
{
    use ShownOnViewPage;

    protected static string $relationship = 'payments';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('applications.bill'))
            ->defaultSort('payment_date', 'desc')
            ->columns([
                TextColumn::make('number')->searchable(),
                TextColumn::make('voucher_no')->label('Voucher')->placeholder('—')->toggleable(),
                TextColumn::make('payment_date')->label('Date')->date('M j, Y')->sortable(),
                TextColumn::make('method')
                    ->formatStateUsing(fn (PaymentMethod $state): string => DocumentStatus::method($state)),
                TextColumn::make('applied_to')->label('Applied to')->wrap()
                    ->state(fn (VendorPayment $record): string => $record->applications
                        ->map(fn (BillApplication $application): ?string => $application->bill?->number)
                        ->filter()->join(', '))
                    ->placeholder('—'),
                TextColumn::make('gross_applied')->label('Gross')->alignEnd()
                    ->formatStateUsing(fn (Money $state): string => Peso::format($state)),
                TextColumn::make('ewt')->label('EWT')->alignEnd()
                    ->formatStateUsing(fn (Money $state): string => $state->isZero() ? '—' : Peso::format($state)),
                TextColumn::make('net_paid')->label('Net paid')->alignEnd()->sortable()
                    ->formatStateUsing(fn (Money $state): string => Peso::format($state)),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => $state === 'posted' ? 'success' : 'gray'),
            ])
            ->recordUrl(fn (VendorPayment $record): ?string => $record->journal_entry_id === null
                ? null
                : JournalEntryResource::getUrl('view', ['record' => $record->journal_entry_id]));
    }
}
