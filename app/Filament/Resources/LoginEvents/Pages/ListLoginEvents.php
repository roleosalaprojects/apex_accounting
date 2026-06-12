<?php

declare(strict_types=1);

namespace App\Filament\Resources\LoginEvents\Pages;

use App\Filament\Resources\LoginEvents\LoginEventResource;
use Filament\Resources\Pages\ListRecords;

class ListLoginEvents extends ListRecords
{
    protected static string $resource = LoginEventResource::class;
}
