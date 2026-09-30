<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Models\Company;
use App\Models\User;
use App\Services\Printing\ReportExporter;
use App\Support\Rbac\RbacRegistry;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Base for on-screen BIR/financial report pages (§12). Renders a filtered table
 * and offers loose-leaf XLSX / PDF exports via ReportExporter.
 */
abstract class ReportPage extends Page
{
    protected string $view = 'filament.reports.report';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    public ?string $from = null;

    public ?string $asOf = null;

    public ?string $entity = null;

    public static function canAccess(): bool
    {
        $company = Filament::getTenant();
        $user = Auth::user();

        return $company instanceof Company && $user instanceof User
            && $user->hasCompanyPermission($company->id, RbacRegistry::REPORTS_VIEW);
    }

    public function mount(): void
    {
        $this->from = Carbon::now()->startOfYear()->toDateString();
        $this->asOf = Carbon::now()->toDateString();
    }

    /**
     * The filter bar: the report's dates and, where it has one, its entity
     * (account, customer, dimension) — bound to $from, $asOf and $entity.
     */
    public function filtersForm(Schema $schema): Schema
    {
        $entity = $this->entityFilter();

        return $schema
            ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
            ->components([
                DatePicker::make('from')->label('From')->live()
                    ->visible($this->usesRange()),
                DatePicker::make('asOf')->label($this->usesRange() ? 'To' : 'As of')->live(),
                Select::make('entity')->label($entity['label'] ?? '')
                    ->options($entity['options'] ?? [])
                    ->placeholder('— select —')
                    ->searchable()
                    ->live()
                    ->visible($entity !== null)
                    ->columnSpan(['default' => 1, 'lg' => 2]),
            ]);
    }

    /** Some reports are as-of only (no range). */
    protected function usesRange(): bool
    {
        return true;
    }

    /**
     * Reports filtered by a single entity (GL account, customer) override this.
     *
     * @return array{label: string, options: array<int|string, string>}|null
     */
    protected function entityFilter(): ?array
    {
        return null;
    }

    /**
     * @return array{columns: array<int, string>, rows: array<int, array<int, string>>, totals?: array<int, string>, meta?: array<string, mixed>}
     */
    abstract protected function payload(): array;

    protected function company(): Company
    {
        /** @var Company $c */
        $c = Filament::getTenant();

        return $c;
    }

    protected function peso(int $minor): string
    {
        return number_format($minor / 100, 2);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'report' => $this->payload(),
            'from' => $this->from,
            'asOf' => $this->asOf,
            'usesRange' => $this->usesRange(),
            'entityFilter' => $this->entityFilter(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('xlsx')->label('Export XLSX')->icon('heroicon-o-table-cells')
                ->action(fn (): StreamedResponse => $this->download('xlsx')),
            Action::make('pdf')->label('Export PDF')->icon('heroicon-o-document-arrow-down')
                ->action(fn (): StreamedResponse => $this->download('pdf')),
        ];
    }

    private function download(string $format): StreamedResponse
    {
        $company = $this->company();
        $payload = $this->payload();
        $header = "{$company->name} — TIN {$company->tin} Branch {$company->branch_code}";
        $title = static::getNavigationLabel().' · '.($this->usesRange() ? "{$this->from} to {$this->asOf}" : "as of {$this->asOf}");

        $rows = $payload['rows'];
        if (! empty($payload['totals'])) {
            $rows[] = $payload['totals'];
        }

        $exporter = app(ReportExporter::class);
        $filename = str(static::getNavigationLabel())->slug().'-'.$this->asOf;

        if ($format === 'xlsx') {
            $bytes = $exporter->toXlsx($header, $title, $payload['columns'], $rows);
            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $name = "{$filename}.xlsx";
        } else {
            $bytes = $exporter->toPdf($header, $title, $payload['columns'], $rows);
            $mime = 'application/pdf';
            $name = "{$filename}.pdf";
        }

        return response()->streamDownload(fn () => print ($bytes), $name, ['Content-Type' => $mime]);
    }
}
