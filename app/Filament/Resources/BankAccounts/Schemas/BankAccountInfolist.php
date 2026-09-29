<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankAccounts\Schemas;

use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Support\FiscalYear;
use App\Filament\Support\KeyFigure;
use App\Filament\Support\Peso;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Reconciliation;
use App\Services\Reports\AccountSummary;
use Carbon\CarbonImmutable;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A bank account's page: the balance per books (always the linked GL account,
 * §16.9), this year's deposits and withdrawals, and the last reconciliation.
 * The postings follow in the LedgerActivity widget.
 */
class BankAccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Summary')->columns(['default' => 2, 'lg' => 4])->schema([
                KeyFigure::make('book_balance', 'Balance per books')
                    ->state(fn (BankAccount $record): string => self::signed(self::summary($record)['balance']))
                    ->color(fn (BankAccount $record): ?string => self::summary($record)['balance'] < 0 ? 'danger' : null)
                    ->belowContent(fn (): string => 'As of '.CarbonImmutable::today()->format('M j, Y')),
                KeyFigure::make('deposits', 'Money in this year')
                    ->state(fn (BankAccount $record): string => Peso::format(self::summary($record)['debits']))
                    ->belowContent(fn (): string => 'Since '.FiscalYear::start()->format('M j, Y')),
                KeyFigure::make('withdrawals', 'Money out this year')
                    ->state(fn (BankAccount $record): string => Peso::format(self::summary($record)['credits']))
                    ->belowContent(fn (): string => 'Since '.FiscalYear::start()->format('M j, Y')),
                KeyFigure::make('last_reconciled', 'Last reconciled')
                    ->state(fn (BankAccount $record): ?string => self::lastReconciliation($record)?->statement_date->format('M j, Y'))
                    ->placeholder('Never')
                    ->belowContent(fn (BankAccount $record): ?string => ($reconciliation = self::lastReconciliation($record)) !== null
                        ? 'Statement balance '.Peso::format($reconciliation->statement_ending_balance)
                        : null),
            ])->columnSpanFull(),
            Section::make('Details')->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                TextEntry::make('bank_name')->label('Bank')->placeholder('—'),
                TextEntry::make('account_no')->label('Account no.')->placeholder('—'),
                TextEntry::make('account.name')->label('GL account')
                    ->formatStateUsing(fn (BankAccount $record): string => "{$record->account?->code} · {$record->account?->name}")
                    ->url(fn (BankAccount $record): ?string => $record->account !== null && AccountResource::canView($record->account)
                        ? AccountResource::getUrl('view', ['record' => $record->account])
                        : null),
                IconEntry::make('is_active')->label('Active')->boolean(),
            ])->columnSpanFull(),
        ]);
    }

    /**
     * @return array{balance: int, debits: int, credits: int, last_entry: string|null}
     */
    private static function summary(BankAccount $bankAccount): array
    {
        return once(function () use ($bankAccount): array {
            /** @var Account $account */
            $account = $bankAccount->account;

            return app(AccountSummary::class)->build(
                $account,
                FiscalYear::start()->toDateString(),
                CarbonImmutable::today()->toDateString(),
            );
        });
    }

    private static function lastReconciliation(BankAccount $bankAccount): ?Reconciliation
    {
        return once(fn (): ?Reconciliation => $bankAccount->reconciliations()
            ->where('status', 'completed')
            ->latest('statement_date')
            ->first());
    }

    private static function signed(int $minor): string
    {
        return ($minor < 0 ? '−' : '').Peso::format(abs($minor));
    }
}
