<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentSequences\Pages;

use App\Filament\Resources\DocumentSequences\DocumentSequenceResource;
use Filament\Resources\Pages\ListRecords;

class ListDocumentSequences extends ListRecords
{
    protected static string $resource = DocumentSequenceResource::class;
}
