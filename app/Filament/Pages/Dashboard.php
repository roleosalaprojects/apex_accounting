<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Accounting dashboard: key figures, trends, receivables and payables, the BIR
 * filing calendar and the bookkeeping to-do list (widgets in app/Filament/Widgets).
 */
class Dashboard extends BaseDashboard
{
    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 3];
    }

    public function getSubheading(): ?string
    {
        $company = Filament::getTenant();

        return $company instanceof Company ? $company->name.' · '.now()->format('l, F j, Y') : null;
    }
}
