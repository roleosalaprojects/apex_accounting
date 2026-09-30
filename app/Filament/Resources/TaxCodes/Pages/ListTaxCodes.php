<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaxCodes\Pages;

use App\Filament\Resources\TaxCodes\TaxCodeResource;
use Filament\Resources\Pages\ListRecords;

class ListTaxCodes extends ListRecords
{
    protected static string $resource = TaxCodeResource::class;
}
