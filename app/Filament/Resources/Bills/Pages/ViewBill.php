<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bills\Pages;

use App\Filament\Resources\Bills\BillResource;
use App\Filament\Support\AttachFilesAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewBill extends ViewRecord
{
    protected static string $resource = BillResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)
            ->load(['vendor', 'lines', 'attachments.uploader']);
    }

    protected function getHeaderActions(): array
    {
        return [
            AttachFilesAction::make(),
        ];
    }
}
