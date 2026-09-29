<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Schemas;

use App\Filament\Support\FiscalYear;
use App\Filament\Support\KeyFigure;
use App\Filament\Support\Peso;
use App\Models\Customer;
use App\Services\Reports\CustomerSummary;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Summary')->columns(['default' => 2, 'lg' => 4])->schema([
                KeyFigure::make('balance_due', 'Balance due')
                    ->state(fn (Customer $record): string => Peso::format(self::summary($record)['balance']))
                    ->belowContent(fn (Customer $record): string => self::count(self::summary($record)['open_invoices'], 'open invoice')),
                KeyFigure::make('overdue', 'Overdue')
                    ->state(fn (Customer $record): string => Peso::format(self::summary($record)['overdue']))
                    ->color(fn (Customer $record): ?string => self::summary($record)['overdue'] > 0 ? 'danger' : null)
                    ->belowContent(fn (Customer $record): string => self::summary($record)['overdue_invoices'] > 0
                        ? self::count(self::summary($record)['overdue_invoices'], 'invoice').' past due'
                        : 'Nothing past due'),
                KeyFigure::make('invoiced', 'Invoiced this year')
                    ->state(fn (Customer $record): string => Peso::format(self::summary($record)['invoiced']))
                    ->belowContent(fn (): string => 'VAT inclusive, since '.FiscalYear::start()->format('M j, Y')),
                KeyFigure::make('last_payment', 'Last payment')
                    ->state(fn (Customer $record): ?string => ($payment = self::summary($record)['last_payment']) !== null
                        ? Peso::format($payment['amount'])
                        : null)
                    ->placeholder('None yet')
                    ->belowContent(fn (Customer $record): ?string => ($payment = self::summary($record)['last_payment']) !== null
                        ? 'Received '.CarbonImmutable::parse($payment['date'])->format('M j, Y')
                        : null),
            ])->columnSpanFull(),
            Section::make('Details')->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                TextEntry::make('code'),
                TextEntry::make('name'),
                TextEntry::make('tin')->label('TIN')->placeholder('—'),
                TextEntry::make('terms_days')->label('Terms')
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Due on receipt' : "Net {$state} days"),
                TextEntry::make('credit_limit')->label('Credit limit')
                    ->formatStateUsing(fn (Money $state): string => Peso::format($state))
                    ->placeholder('No limit'),
                IconEntry::make('is_withholding_agent')->label('Withholding agent')->boolean(),
                TextEntry::make('createdBy.name')->label('Created by')->placeholder('—'),
                TextEntry::make('address')->placeholder('—')->columnSpanFull(),
            ])->columnSpanFull(),
        ]);
    }

    /**
     * @return array{balance: int, overdue: int, open_invoices: int, overdue_invoices: int, invoiced: int, last_payment: array{date: string, amount: int}|null}
     */
    private static function summary(Customer $customer): array
    {
        return once(fn (): array => app(CustomerSummary::class)->build(
            $customer,
            FiscalYear::start()->toDateString(),
            CarbonImmutable::today()->toDateString(),
        ));
    }

    private static function count(int $count, string $noun): string
    {
        return $count.' '.str($noun)->plural($count);
    }
}
