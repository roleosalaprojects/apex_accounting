<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Support\FiscalYear;
use App\Filament\Support\PrintAction;
use App\Models\Customer;
use App\Services\Printing\PrintCustomerStatement;
use Carbon\CarbonImmutable;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PrintAction::make('statement', 'Statement',
                fn (Customer $customer): string => app(PrintCustomerStatement::class)->render(
                    $customer, FiscalYear::start()->toDateString(), CarbonImmutable::today()->toDateString(),
                ),
                fn (Customer $customer): string => 'SOA-'.$customer->code.'-'.CarbonImmutable::today()->format('Ymd').'.pdf',
            )->icon('heroicon-o-document-text'),
            EditAction::make(),
        ];
    }
}
