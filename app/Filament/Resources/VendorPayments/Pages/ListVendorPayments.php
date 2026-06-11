<?php

declare(strict_types=1);

namespace App\Filament\Resources\VendorPayments\Pages;

use App\Filament\Resources\VendorPayments\VendorPaymentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVendorPayments extends ListRecords
{
    protected static string $resource = VendorPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Pay bill')];
    }
}
