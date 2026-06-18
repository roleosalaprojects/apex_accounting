<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaxReturns\Pages;

use App\Filament\Resources\TaxReturns\TaxReturnResource;
use App\Models\Company;
use App\Services\Tax\SlspDatExporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListTaxReturns extends ListRecords
{
    protected static string $resource = TaxReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Prepare return'),
            $this->slspAction('sales', 'Export SLSP — Sales'),
            $this->slspAction('purchases', 'Export SLSP — Purchases'),
        ];
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
