<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Services\Reports\DimensionProfitAndLossReport;
use Carbon\CarbonImmutable;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared view page for the reporting dimensions (department, project, fund,
 * branch): the record's details and the results of the lines tagged with it
 * this year, last year and all time. The tagged lines themselves follow in
 * the LedgerActivity widget.
 */
final class DimensionInfolist
{
    public static function configure(Schema $schema, string $dimension): Schema
    {
        return $schema->components([
            Section::make('Details')->columns(4)->schema([
                TextEntry::make('code'),
                TextEntry::make('name'),
                IconEntry::make('is_active')->label('Active')->boolean(),
                TextEntry::make('createdBy.name')->label('Created by')->placeholder('—'),
            ])->columnSpanFull(),
            Section::make('Results')
                ->description("Income and expenses on journal lines tagged with this {$dimension}.")
                ->schema([
                    ViewEntry::make('results')->hiddenLabel()
                        ->view('filament.infolists.results-summary')
                        ->state(fn (Model $record): array => self::results($record, $dimension)),
                ])->columnSpanFull(),
        ]);
    }

    /**
     * @return list<array{period: string, range: string, income: string, expenses: string, net: string, loss: bool}>
     */
    private static function results(Model $record, string $dimension): array
    {
        $today = CarbonImmutable::today();
        $yearStart = FiscalYear::start($today);

        $periods = [
            'This year' => [$yearStart, $today],
            'Last year' => [$yearStart->subYear(), $yearStart->subDay()],
            'All time' => [CarbonImmutable::create(1900, 1, 1), $today],
        ];

        $report = app(DimensionProfitAndLossReport::class);
        $rows = [];
        foreach ($periods as $label => [$from, $to]) {
            $totals = $report->totalsFor((int) $record->getAttribute('company_id'), $dimension, (int) $record->getKey(), $from->toDateString(), $to->toDateString());

            $rows[] = [
                'period' => $label,
                'range' => $label === 'All time' ? '' : $from->format('M j, Y').' – '.$to->format('M j, Y'),
                'income' => Peso::format($totals['income']),
                'expenses' => Peso::format($totals['expenses']),
                'net' => ($totals['net'] < 0 ? '−' : '').Peso::format(abs($totals['net'])),
                'loss' => $totals['net'] < 0,
            ];
        }

        return $rows;
    }
}
