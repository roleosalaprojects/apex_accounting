<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Actions\Tax\AllocateCommonInputVat;
use App\Models\User;
use App\Services\Reports\VatSummaryReport;
use App\Support\Rbac\RbacRegistry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Throwable;

class VatSummary extends ReportPage
{
    protected static ?string $navigationLabel = '2550Q VAT Summary';

    protected static ?int $navigationSort = 8;

    public function getTitle(): string
    {
        return '2550Q VAT Summary';
    }

    protected function getHeaderActions(): array
    {
        $allocate = Action::make('allocate')
            ->label('Allocate Common Input VAT')
            ->icon('heroicon-o-calculator')
            ->color('warning')
            ->visible(function (): bool {
                /** @var User|null $user */
                $user = Auth::user();

                return $user?->hasCompanyPermission($this->company()->id, RbacRegistry::TAX_VIEW) === true;
            })
            ->requiresConfirmation()
            ->modalDescription(function (): string {
                $asOf = Carbon::parse($this->asOf);
                $quarter = (int) ceil($asOf->month / 3);

                return "Allocates Q{$quarter} {$asOf->year} common input VAT between creditable and non-creditable based on the VATable/exempt sales mix. Idempotent — re-running an allocated quarter fails.";
            })
            ->action(function (): void {
                /** @var User $user */
                $user = Auth::user();
                $asOf = Carbon::parse($this->asOf);
                $quarter = (int) ceil($asOf->month / 3);

                try {
                    $allocation = app(AllocateCommonInputVat::class)->handle($this->company(), $asOf->year, $quarter, $user);
                    Notification::make()->success()
                        ->title('Common input VAT allocated')
                        ->body("Allocation #{$allocation->id} posted for Q{$quarter} {$asOf->year}.")
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()->danger()->title('Allocation failed')->body($e->getMessage())->send();
                }
            });

        return [$allocate, ...parent::getHeaderActions()];
    }

    protected function payload(): array
    {
        $asOf = Carbon::parse($this->asOf);
        $quarter = (int) ceil($asOf->month / 3);
        $r = app(VatSummaryReport::class)->build($this->company()->id, $asOf->year, $quarter, (string) $this->from, (string) $this->asOf);

        $rows = [
            ['VAT-exempt sales', $this->peso($r['exempt_sales'])],
            ['Zero-rated sales', $this->peso($r['zero_rated_sales'])],
            ['VATable sales', $this->peso($r['vatable_sales'])],
            ['Output VAT', $this->peso($r['output_vat'])],
            ['Creditable input VAT (direct + allocated common)', $this->peso($r['creditable_input_vat'])],
            ['VAT payable', $this->peso($r['vat_payable'])],
            ['Excess input VAT carryover', $this->peso($r['carryover'])],
        ];

        return [
            'columns' => ['Line', 'Amount'],
            'rows' => $rows,
            'meta' => ['label' => 'Quarter '.$quarter.' '.$asOf->year.($r['allocation_id'] ? ' · allocation #'.$r['allocation_id'] : '')],
        ];
    }
}
