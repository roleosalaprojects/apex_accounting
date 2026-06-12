<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringTemplates\Pages;

use App\Filament\Resources\RecurringTemplates\RecurringTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditRecurringTemplate extends EditRecord
{
    protected static string $resource = RecurringTemplateResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['payload'] = filled($data['payload'] ?? null)
            ? json_encode($data['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['payload'] = filled($data['payload'] ?? null)
            ? json_decode((string) $data['payload'], true)
            : null;

        return $data;
    }
}
