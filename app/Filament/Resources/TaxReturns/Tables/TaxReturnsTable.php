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
                    ->formatStateUsing(fn (string $state): string => TaxReturnType::tryFrom($state)?->label() ?? $state),
                TextColumn::make('fiscal_year')->label('FY')->sortable(),
                TextColumn::make('quarter')->label('Qtr')->formatStateUsing(fn (?int $state): string => $state ? "Q{$state}" : '—'),
                TextColumn::make('period_start')->label('Period')->date()
                    ->description(fn (TaxReturn $r): string => 'to '.$r->period_end->toDateString()),
                TextColumn::make('headline')->label('Amount due')
                    ->state(fn (TaxReturn $r): string => number_format($r->headlineAmount() / 100, 2)),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => $state === 'filed' ? 'success' : 'gray'),
                TextColumn::make('created_at')->dateTime()->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
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

    private static function labelFor(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
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
