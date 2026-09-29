<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Support\DocumentStatus;
use App\Filament\Support\Peso;
use App\Filament\Support\ShownOnViewPage;
use App\Models\Invoice;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** The customer's invoices, newest first, with what is still owed on each. */
class InvoicesRelationManager extends RelationManager
{
    use ShownOnViewPage;

    protected static string $relationship = 'invoices';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withSum('paymentApplications', 'amount')
                ->withSum('creditMemoApplications', 'amount'))
            ->defaultSort('invoice_date', 'desc')
            ->columns([
                TextColumn::make('number')->searchable()->placeholder('Draft'),
                TextColumn::make('invoice_date')->label('Date')->date('M j, Y')->sortable(),
                TextColumn::make('due_date')->label('Due')->date('M j, Y')->placeholder('—')->sortable(),
                TextColumn::make('total')->alignEnd()->sortable()
                    ->formatStateUsing(fn (Money $state): string => Peso::format($state)),
                TextColumn::make('balance')->label('Balance')->alignEnd()
                    ->state(fn (Invoice $record): ?int => self::outstanding($record))
                    ->formatStateUsing(fn (int $state): string => Peso::format($state))
                    ->placeholder('—'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (InvoiceStatus $state): string => DocumentStatus::label($state))
                    ->color(fn (InvoiceStatus $state): string => DocumentStatus::color($state)),
            ])
            ->filters([
                SelectFilter::make('status')->options(DocumentStatus::options()),
            ])
            ->recordUrl(fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record]));
    }

    /** Still owed on a posted invoice; null for drafts and voided ones. */
    private static function outstanding(Invoice $invoice): ?int
    {
        if (! in_array($invoice->status, [InvoiceStatus::Posted, InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid], true)) {
            return null;
        }

        return $invoice->total->minor
            - (int) $invoice->getAttribute('payment_applications_sum_amount')
            - (int) $invoice->getAttribute('credit_memo_applications_sum_amount');
    }
}
