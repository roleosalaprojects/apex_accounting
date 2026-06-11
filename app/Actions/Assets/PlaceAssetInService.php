<?php

declare(strict_types=1);

namespace App\Actions\Assets;

use App\Enums\AssetStatus;
use App\Models\Asset;
use RuntimeException;

/**
 * Places an asset in service (§10.2): validates accounts and useful life, then
 * marks it depreciable from the in-service date.
 */
final class PlaceAssetInService
{
    public function handle(Asset $asset, string $inServiceDate): Asset
    {
        if ($asset->status !== AssetStatus::Draft) {
            throw new RuntimeException('Only a draft asset can be placed in service.');
        }
        if ($asset->useful_life_months < 1) {
            throw new RuntimeException('Useful life must be at least one month.');
        }
        if ($asset->depreciableBase() < 0) {
            throw new RuntimeException('Salvage value cannot exceed acquisition cost.');
        }

        $asset->forceFill([
            'status' => AssetStatus::InService,
            'in_service_date' => $inServiceDate,
        ])->save();

        return $asset;
    }
}
