<?php

declare(strict_types=1);

namespace App\Filament\Resources\PosZReadings\Pages;

use App\Filament\Resources\PosZReadings\PosZReadingResource;
use Filament\Resources\Pages\ListRecords;

class ListPosZReadings extends ListRecords
{
    protected static string $resource = PosZReadingResource::class;
}
