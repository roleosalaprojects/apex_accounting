<?php

declare(strict_types=1);

namespace App\Filament\Resources\AssetCategories\Pages;

use App\Filament\Resources\AssetCategories\AssetCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssetCategory extends CreateRecord
{
    protected static string $resource = AssetCategoryResource::class;
}
