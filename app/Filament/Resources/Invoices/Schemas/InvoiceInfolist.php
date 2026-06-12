<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Schemas;

use App\Filament\Support\AttachmentsSection;
use App\Filament\Support\Peso;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Invoice')->columns(4)->schema([
                TextEntry::make('number'),
                TextEntry::make('customer.name')->label('Customer'),
                TextEntry::make('invoice_date')->date(),
                TextEntry::make('due_date')->date()->placeholder('—'),
                TextEntry::make('status')->badge(),
                TextEntry::make('pricing_mode')->label('Pricing'),
                TextEntry::make('journalEntry.number')->label('Journal entry')->placeholder('—'),
            ]),
            Section::make('Lines')->schema([
                RepeatableEntry::make('lines')->hiddenLabel()->columns(6)->schema([
                    TextEntry::make('description'),
                    TextEntry::make('qty')->label('Qty'),
                    TextEntry::make('unit_price')->label('Unit price')->formatStateUsing(fn ($state) => Peso::format($state)),
                    TextEntry::make('taxCode.code')->label('Tax')->placeholder('—'),
                    TextEntry::make('line_total')->label('Line total')->formatStateUsing(fn ($state) => Peso::format($state)),
                    TextEntry::make('vat_amount')->label('VAT')->formatStateUsing(fn ($state) => Peso::format($state)),
                ]),
            ]),
            Section::make('Totals')->columns(5)->schema([
                TextEntry::make('vatable_sales')->label('VATable')->formatStateUsing(fn ($state) => Peso::format($state)),
                TextEntry::make('vat_amount')->label('Output VAT')->formatStateUsing(fn ($state) => Peso::format($state)),
                TextEntry::make('exempt_sales')->label('VAT-exempt')->formatStateUsing(fn ($state) => Peso::format($state)),
                TextEntry::make('zero_rated_sales')->label('Zero-rated')->formatStateUsing(fn ($state) => Peso::format($state)),
                TextEntry::make('total')->label('Total')->weight('bold')->formatStateUsing(fn ($state) => Peso::format($state)),
            ]),
            AttachmentsSection::make(),
        ]);
    }
}
