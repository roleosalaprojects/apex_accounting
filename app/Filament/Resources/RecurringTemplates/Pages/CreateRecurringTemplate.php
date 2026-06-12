<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringTemplates\Pages;

use App\Filament\Resources\RecurringTemplates\RecurringTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecurringTemplate extends CreateRecord
{
    protected static string $resource = RecurringTemplateResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['payload'] = isset($data['payload']) && is_string($data['payload']) && $data['payload'] !== ''
            ? json_decode($data['payload'], true)
            : null;
        $data['next_run_on'] = $data['starts_on'];

        return $data;
    }
}
