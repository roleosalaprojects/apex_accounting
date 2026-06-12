<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets\Pages;

use App\Enums\AssetStatus;
use App\Filament\Resources\Assets\AssetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['acquisition_cost'] = (int) round(((float) $data['acquisition_cost']) * 100);
        $data['salvage_value'] = (int) round(((float) ($data['salvage_value'] ?? 0)) * 100);
        $data['status'] = AssetStatus::Draft->value;

        return $data;
    }
}
