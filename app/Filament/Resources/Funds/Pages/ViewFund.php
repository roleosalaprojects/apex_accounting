<?php

declare(strict_types=1);

namespace App\Filament\Resources\Funds\Pages;

use App\Filament\Resources\Funds\FundResource;
use App\Filament\Support\Widgets\LedgerActivity;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFund extends ViewRecord
{
    protected static string $resource = FundResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }

    protected function getFooterWidgets(): array
    {
        return [LedgerActivity::class];
    }
}
