<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\Actions\AssetActions;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Support\LoadsRecordRelations;
use Filament\Resources\Pages\ViewRecord;

class ViewAsset extends ViewRecord
{
    use LoadsRecordRelations;

    protected static string $resource = AssetResource::class;

    protected function recordRelations(): array
    {
        return ['category', 'department', 'project', 'fund', 'branch'];
    }

    protected function getHeaderActions(): array
    {
        return [
            AssetActions::placeInService(),
            AssetActions::dispose(),
        ];
    }
}
