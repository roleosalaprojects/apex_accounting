<?php

declare(strict_types=1);

namespace App\Filament\Resources\Departments\Pages;

use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Support\Widgets\LedgerActivity;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDepartment extends ViewRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }

    protected function getFooterWidgets(): array
    {
        return [LedgerActivity::class];
    }
}
