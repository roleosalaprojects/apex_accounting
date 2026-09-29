<?php

declare(strict_types=1);

namespace App\Filament\Resources\Vendors\RelationManagers;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Bills\BillResource;
use App\Filament\Support\DocumentStatus;
use App\Filament\Support\Peso;
use App\Filament\Support\ShownOnViewPage;
use App\Models\Bill;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** The vendor's bills, newest first, with what is still owed on each. */
class BillsRelationManager extends RelationManager
{
    use ShownOnViewPage;

    protected static string $relationship = 'bills';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withSum('applications', 'amount')
                ->withSum('debitMemoApplications', 'amount'))
            ->defaultSort('bill_date', 'desc')
            ->columns([
                TextColumn::make('number')->searchable()->placeholder('Draft'),
                TextColumn::make('bill_date')->label('Date')->date('M j, Y')->sortable(),
                TextColumn::make('due_date')->label('Due')->date('M j, Y')->placeholder('—')->sortable(),
                TextColumn::make('total')->alignEnd()->sortable()
                    ->formatStateUsing(fn (Money $state): string => Peso::format($state)),
                TextColumn::make('balance')->label('Balance')->alignEnd()
                    ->state(fn (Bill $record): ?int => self::outstanding($record))
                    ->formatStateUsing(fn (int $state): string => Peso::format($state))
                    ->placeholder('—'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (InvoiceStatus $state): string => DocumentStatus::label($state))
                    ->color(fn (InvoiceStatus $state): string => DocumentStatus::color($state)),
            ])
            ->filters([
                SelectFilter::make('status')->options(DocumentStatus::options()),
            ])
            ->recordUrl(fn (Bill $record): string => BillResource::getUrl('view', ['record' => $record]));
    }

    /** Still owed on a posted bill; null for drafts and voided ones. */
    private static function outstanding(Bill $bill): ?int
    {
        if (! in_array($bill->status, [InvoiceStatus::Posted, InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid], true)) {
            return null;
        }

        return $bill->total->minor
            - (int) $bill->getAttribute('applications_sum_amount')
            - (int) $bill->getAttribute('debit_memo_applications_sum_amount');
    }
}
