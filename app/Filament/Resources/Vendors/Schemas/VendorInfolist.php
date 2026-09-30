<?php

declare(strict_types=1);

namespace App\Filament\Resources\Vendors\Schemas;

use App\Filament\Support\FiscalYear;
use App\Filament\Support\KeyFigure;
use App\Filament\Support\Peso;
use App\Models\Vendor;
use App\Services\Reports\VendorSummary;
use Carbon\CarbonImmutable;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Summary')->columns(['default' => 2, 'lg' => 4])->schema([
                KeyFigure::make('balance_owed', 'Balance owed')
                    ->state(fn (Vendor $record): string => Peso::format(self::summary($record)['balance']))
                    ->belowContent(fn (Vendor $record): string => self::count(self::summary($record)['open_bills'], 'open bill')),
                KeyFigure::make('overdue', 'Overdue')
                    ->state(fn (Vendor $record): string => Peso::format(self::summary($record)['overdue']))
                    ->color(fn (Vendor $record): ?string => self::summary($record)['overdue'] > 0 ? 'danger' : null)
                    ->belowContent(fn (Vendor $record): string => self::summary($record)['overdue_bills'] > 0
                        ? self::count(self::summary($record)['overdue_bills'], 'bill').' past due'
                        : 'Nothing past due'),
                KeyFigure::make('billed', 'Billed this year')
                    ->state(fn (Vendor $record): string => Peso::format(self::summary($record)['billed']))
                    ->belowContent(fn (Vendor $record): string => 'Since '.FiscalYear::start()->format('M j, Y')
                        .(self::summary($record)['ewt'] > 0 ? ' · '.Peso::format(self::summary($record)['ewt']).' EWT withheld' : '')),
                KeyFigure::make('last_payment', 'Last payment')
                    ->state(fn (Vendor $record): ?string => ($payment = self::summary($record)['last_payment']) !== null
                        ? Peso::format($payment['amount'])
                        : null)
                    ->placeholder('None yet')
                    ->belowContent(fn (Vendor $record): ?string => ($payment = self::summary($record)['last_payment']) !== null
                        ? 'Paid '.CarbonImmutable::parse($payment['date'])->format('M j, Y')
                        : null),
            ])->columnSpanFull(),
            Section::make('Details')->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                TextEntry::make('code'),
                TextEntry::make('name'),
                TextEntry::make('tin')->label('TIN')->placeholder('—'),
                TextEntry::make('terms_days')->label('Terms')
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Due on receipt' : "Net {$state} days"),
                IconEntry::make('is_vat_registered')->label('VAT registered')->boolean(),
                TextEntry::make('defaultWithholdingCode.name')->label('Default EWT')
                    ->formatStateUsing(fn (Vendor $record): string => $record->defaultWithholdingCode?->atc.' · '.$record->defaultWithholdingCode?->name)
                    ->placeholder('None'),
                TextEntry::make('createdBy.name')->label('Created by')->placeholder('—'),
                TextEntry::make('email')->placeholder('—')->copyable(),
                TextEntry::make('contact_person')->label('Contact person')->placeholder('—'),
                TextEntry::make('address')->placeholder('—')->columnSpan(2),
            ])->columnSpanFull(),
        ]);
    }

    /**
     * @return array{balance: int, overdue: int, open_bills: int, overdue_bills: int, billed: int, ewt: int, last_payment: array{date: string, amount: int}|null}
     */
    private static function summary(Vendor $vendor): array
    {
        return once(fn (): array => app(VendorSummary::class)->build(
            $vendor,
            FiscalYear::start()->toDateString(),
            CarbonImmutable::today()->toDateString(),
        ));
    }

    private static function count(int $count, string $noun): string
    {
        return $count.' '.str($noun)->plural($count);
    }
}
