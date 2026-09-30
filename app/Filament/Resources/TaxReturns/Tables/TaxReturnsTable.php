<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaxReturns\Tables;

use App\Enums\TaxReturnType;
use App\Models\Company;
use App\Models\TaxReturn;
use App\Services\Printing\ReportExporter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxReturnsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->badge()
                    ->formatStateUsing(fn (string $state): string => TaxReturnType::tryFrom($state)?->form() ?? $state)
                    ->description(fn (TaxReturn $r): string => $r->returnType()?->title() ?? ''),
                TextColumn::make('fiscal_year')->label('FY')->sortable(),
                TextColumn::make('quarter')->label('Qtr')->formatStateUsing(fn (?int $state): string => $state ? "Q{$state}" : '—'),
                TextColumn::make('period_start')->label('Period')->sortable()
                    ->formatStateUsing(fn (TaxReturn $r): string => self::period($r)),
                TextColumn::make('headline')->label('Amount due')
                    ->state(fn (TaxReturn $r): string => number_format($r->headlineAmount() / 100, 2)),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => $state === 'filed' ? 'success' : 'gray'),
                TextColumn::make('created_at')->dateTime()->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('period_end', 'desc')
            ->recordActions([
                Action::make('pdf')->label('PDF')->icon('heroicon-o-document-arrow-down')
                    ->action(function (TaxReturn $record): StreamedResponse {
                        $company = Company::query()->findOrFail($record->company_id);
                        $header = "{$company->name} — TIN {$company->tin} Branch {$company->branch_code}";
                        $title = ($record->returnType()?->label() ?? $record->type).' · '
                            .$record->period_start->toDateString().' to '.$record->period_end->toDateString();

                        $rows = [];
                        foreach ($record->figures as $key => $value) {
                            $rows[] = [self::labelFor((string) $key), self::formatFigure($value)];
                        }

                        $bytes = app(ReportExporter::class)->toPdf($header, $title, ['Figure', 'Value'], $rows);

                        return response()->streamDownload(
                            fn () => print ($bytes),
                            "tax-return-{$record->type}-{$record->id}.pdf",
                            ['Content-Type' => 'application/pdf'],
                        );
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** "May 1 – May 31, 2026", or "Jan 1, 2025 – Dec 31, 2025" when the period straddles a year end. */
    private static function period(TaxReturn $return): string
    {
        $start = $return->period_start;
        $end = $return->period_end;

        return $start->year === $end->year
            ? $start->format('M j').' – '.$end->format('M j, Y')
            : $start->format('M j, Y').' – '.$end->format('M j, Y');
    }

    private static function labelFor(string $key): string
    {
        return match ($key) {
            'total_ewt' => 'Total EWT withheld',
            'remitted_0619e' => 'Remitted on 0619-E (first two months)',
            'tax_due' => 'Tax due',
            'total_base' => 'Total income payments',
            'by_atc' => 'ATCs',
            default => ucwords(str_replace('_', ' ', $key)),
        };
    }

    private static function formatFigure(mixed $value): string
    {
        if (is_int($value)) {
            return number_format($value / 100, 2);
        }
        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
        }
        if (is_array($value)) {
            return count($value).' item(s)';
        }

        return (string) $value;
    }
}
