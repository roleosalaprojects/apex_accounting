<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaxCodes\Pages;

use App\Filament\Resources\TaxCodes\TaxCodeResource;
use Filament\Resources\Pages\EditRecord;

class EditTaxCode extends EditRecord
{
    protected static string $resource = TaxCodeResource::class;

    protected function getRedirectUrl(): string
    {
        return TaxCodeResource::getUrl('index');
    }
}
