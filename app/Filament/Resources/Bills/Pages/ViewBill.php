<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bills\Pages;

use App\Filament\Resources\Bills\Actions\PayBillAction;
use App\Filament\Resources\Bills\BillResource;
use App\Filament\Support\AttachFilesAction;
use App\Filament\Support\LoadsRecordRelations;
use Filament\Resources\Pages\ViewRecord;

class ViewBill extends ViewRecord
{
    use LoadsRecordRelations;

    protected static string $resource = BillResource::class;

    protected function recordRelations(): array
    {
        return ['vendor', 'lines', 'attachments.uploader'];
    }

    protected function getHeaderActions(): array
    {
        return [
            PayBillAction::make(),
            AttachFilesAction::make(),
        ];
    }
}
