<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaxReturns\Pages;

use App\Enums\TaxReturnType;
use App\Filament\Resources\TaxReturns\TaxReturnResource;
use App\Models\Company;
use App\Services\Tax\TaxReturnService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTaxReturn extends CreateRecord
{
    protected static string $resource = TaxReturnResource::class;

    /**
     * Compute and snapshot the figures for the chosen period at creation time.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var Company $company */
        $company = Filament::getTenant();
        $type = TaxReturnType::from($data['type']);
        $fiscalYear = (int) $data['fiscal_year'];
        $quarter = (int) $data['quarter'];

        $service = app(TaxReturnService::class);
        ['from' => $from, 'to' => $to] = $service->quarterRange($company, $fiscalYear, $quarter);

        $data['period_start'] = $from;
        $data['period_end'] = $to;
        $data['figures'] = $service->compute($company, $type, $fiscalYear, $quarter);
        $data['status'] = 'draft';
        $data['created_by'] = Auth::id();

        return $data;
    }
}
