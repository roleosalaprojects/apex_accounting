<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * For relation managers that show a record's history: list them on its view
 * page only, keeping the edit page to the form.
 */
trait ShownOnViewPage
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, ViewRecord::class)
            && parent::canViewForRecord($ownerRecord, $pageClass);
    }
}
