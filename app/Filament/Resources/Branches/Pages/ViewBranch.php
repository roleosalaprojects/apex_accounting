<?php

declare(strict_types=1);

namespace App\Filament\Resources\Branches\Pages;

use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Support\Widgets\LedgerActivity;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBranch extends ViewRecord
{
    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }

    protected function getFooterWidgets(): array
    {
        return [LedgerActivity::class];
    }
}
