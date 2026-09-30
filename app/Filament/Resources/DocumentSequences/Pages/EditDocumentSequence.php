<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentSequences\Pages;

use App\Filament\Resources\DocumentSequences\DocumentSequenceResource;
use Filament\Resources\Pages\EditRecord;

class EditDocumentSequence extends EditRecord
{
    protected static string $resource = DocumentSequenceResource::class;

    protected function getRedirectUrl(): string
    {
        return DocumentSequenceResource::getUrl('index');
    }
}
