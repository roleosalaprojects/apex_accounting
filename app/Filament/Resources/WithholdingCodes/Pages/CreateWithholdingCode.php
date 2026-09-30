<?php

declare(strict_types=1);

namespace App\Filament\Resources\WithholdingCodes\Pages;

use App\Filament\Resources\WithholdingCodes\WithholdingCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWithholdingCode extends CreateRecord
{
    protected static string $resource = WithholdingCodeResource::class;

    protected function getRedirectUrl(): string
    {
        return WithholdingCodeResource::getUrl('index');
    }
}
