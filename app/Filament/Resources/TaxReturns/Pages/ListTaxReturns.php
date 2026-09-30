<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaxReturns\Pages;

use App\Filament\Resources\TaxReturns\TaxReturnResource;
use App\Models\Company;
use App\Services\Tax\AlphalistExporter;
use App\Services\Tax\SlspDatExporter;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListTaxReturns extends ListRecords
{
    protected static string $resource = TaxReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Prepare return'),
            ActionGroup::make([
                $this->slspAction('sales', 'SLSP — Sales'),
                $this->slspAction('purchases', 'SLSP — Purchases'),
                $this->alphalistAction('QAP', 'EWT Alphalist — QAP (quarterly)', now()->startOfQuarter(), now()->endOfQuarter()),
                $this->alphalistAction('1604E', 'EWT Alphalist — 1604-E (annual)', now()->subYear()->startOfYear(), now()->subYear()->endOfYear()),
            ])->label('BIR exports')->icon('heroicon-o-arrow-down-tray')->button()->color('gray'),
        ];
    }

    private function alphalistAction(string $form, string $label, Carbon $from, Carbon $to): Action
    {
        return Action::make("alphalist_{$form}")
            ->label($label)
            ->icon('heroicon-o-arrow-down-tray')
            ->schema([
                DatePicker::make('from')->required()->default($from),
                DatePicker::make('to')->required()->default($to),
            ])
            ->action(function (array $data) use ($form): StreamedResponse {
                /** @var Company $company */
                $company = Filament::getTenant();
                $content = app(AlphalistExporter::class)->ewt($company, $data['from'], $data['to'], $form);

                return response()->streamDownload(
                    fn () => print ($content),
                    'ewt-alphalist-'.strtolower($form)."-{$data['to']}.dat",
                    ['Content-Type' => 'text/plain'],
                );
            });
    }

    private function slspAction(string $kind, string $label): Action
    {
        return Action::make("slsp_{$kind}")
            ->label($label)
            ->icon('heroicon-o-arrow-down-tray')
            ->schema([
                DatePicker::make('from')->required()->default(now()->startOfQuarter()),
                DatePicker::make('to')->required()->default(now()->endOfQuarter()),
            ])
            ->action(function (array $data) use ($kind): StreamedResponse {
                /** @var Company $company */
                $company = Filament::getTenant();
                $exporter = app(SlspDatExporter::class);
                $content = $kind === 'sales'
                    ? $exporter->sales($company, $data['from'], $data['to'])
                    : $exporter->purchases($company, $data['from'], $data['to']);

                return response()->streamDownload(
                    fn () => print ($content),
                    "slsp-{$kind}-{$data['to']}.dat",
                    ['Content-Type' => 'text/plain'],
                );
            });
    }
}
