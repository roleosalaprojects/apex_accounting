<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Schemas;

use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Filament\Support\FiscalYear;
use App\Filament\Support\KeyFigure;
use App\Filament\Support\Peso;
use App\Models\Account;
use App\Services\Reports\AccountSummary;
use Carbon\CarbonImmutable;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * An account's page: its balance (or, for income and expense accounts, this
 * year's net), this year's debits and credits, and its setup. The postings
 * themselves follow in the LedgerActivity widget.
 */
class AccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Summary')->columns(['default' => 2, 'lg' => 4])->schema([
                KeyFigure::make('balance', 'Balance')
                    ->hidden(fn (Account $record): bool => $record->type->isNominal())
                    ->state(fn (Account $record): string => Peso::format(abs(self::summary($record)['balance'])))
                    ->color(fn (Account $record): ?string => self::isContrary($record, self::summary($record)['balance']) ? 'warning' : null)
                    ->belowContent(fn (Account $record): string => self::side($record, self::summary($record)['balance'], '%s balance')
                        .' as of '.CarbonImmutable::today()->format('M j, Y')),
                KeyFigure::make('net_this_year', 'Net this year')
                    ->visible(fn (Account $record): bool => $record->type->isNominal())
                    ->state(fn (Account $record): string => Peso::format(abs(self::net($record))))
                    ->color(fn (Account $record): ?string => self::isContrary($record, self::net($record)) ? 'warning' : null)
                    ->belowContent(fn (Account $record): string => self::side($record, self::net($record), 'net %s')
                        .' since '.FiscalYear::start()->format('M j, Y')),
                KeyFigure::make('debits', 'Debits this year')
                    ->state(fn (Account $record): string => Peso::format(self::summary($record)['debits']))
                    ->belowContent(fn (): string => 'Since '.FiscalYear::start()->format('M j, Y')),
                KeyFigure::make('credits', 'Credits this year')
                    ->state(fn (Account $record): string => Peso::format(self::summary($record)['credits']))
                    ->belowContent(fn (): string => 'Since '.FiscalYear::start()->format('M j, Y')),
                KeyFigure::make('last_entry', 'Last entry')
                    ->state(fn (Account $record): ?string => ($date = self::summary($record)['last_entry']) !== null
                        ? CarbonImmutable::parse($date)->format('M j, Y')
                        : null)
                    ->placeholder('No postings yet')
                    ->belowContent(fn (Account $record): ?string => ($date = self::summary($record)['last_entry']) !== null
                        ? self::ago(CarbonImmutable::parse($date))
                        : null),
            ])->columnSpanFull(),
            Section::make('Details')->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                TextEntry::make('code'),
                TextEntry::make('name'),
                TextEntry::make('type')->formatStateUsing(fn (AccountType $state): string => self::words($state->value)),
                TextEntry::make('subtype')->formatStateUsing(fn (AccountSubtype $state): string => self::words($state->value)),
                TextEntry::make('normal_balance')->label('Normal balance')
                    ->formatStateUsing(fn (NormalBalance $state): string => ucfirst($state->value)),
                TextEntry::make('parent.name')->label('Parent account')
                    ->formatStateUsing(fn (Account $record): string => "{$record->parent?->code} · {$record->parent?->name}")
                    ->placeholder('—'),
                IconEntry::make('is_active')->label('Active')->boolean(),
                IconEntry::make('is_system')->label('System account')->boolean(),
                TextEntry::make('createdBy.name')->label('Created by')->placeholder('—'),
                TextEntry::make('description')->placeholder('—')->columnSpanFull(),
            ])->columnSpanFull(),
        ]);
    }

    /**
     * @return array{balance: int, debits: int, credits: int, last_entry: string|null}
     */
    private static function summary(Account $account): array
    {
        return once(fn (): array => app(AccountSummary::class)->build(
            $account,
            FiscalYear::start()->toDateString(),
            CarbonImmutable::today()->toDateString(),
        ));
    }

    /** This year's movement, debit-positive. */
    private static function net(Account $account): int
    {
        return self::summary($account)['debits'] - self::summary($account)['credits'];
    }

    /** "Debit balance" / "Net credit", noting an amount on the unusual side. */
    private static function side(Account $account, int $signed, string $noun): string
    {
        if ($signed === 0) {
            return 'Nil';
        }

        $side = $signed > 0 ? 'debit' : 'credit';
        $text = ucfirst(sprintf($noun, $side));

        return self::isContrary($account, $signed) ? "{$text}, opposite to normal" : $text;
    }

    private static function isContrary(Account $account, int $signed): bool
    {
        return $signed !== 0 && ($signed > 0) !== ($account->normal_balance === NormalBalance::Debit);
    }

    private static function ago(CarbonImmutable $date): string
    {
        $days = (int) $date->diffInDays(CarbonImmutable::today());

        return match (true) {
            $days === 0 => 'Today',
            $days === 1 => 'Yesterday',
            $days < 45 => "{$days} days ago",
            default => $date->diffForHumans(CarbonImmutable::today(), ['syntax' => CarbonImmutable::DIFF_RELATIVE_TO_NOW, 'parts' => 1]),
        };
    }

    private static function words(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }
}
