<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Models\Vendor;
use App\Services\Reports\VendorStatement;

class VendorStatementPage extends ReportPage
{
    protected static ?string $navigationLabel = 'Vendor Statement';

    protected static ?int $navigationSort = 16;

    public function getTitle(): string
    {
        return 'Vendor Statement';
    }

    protected function entityFilter(): ?array
    {
        return [
            'label' => 'Vendor',
            'options' => Vendor::query()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    protected function payload(): array
    {
        $columns = ['Date', 'Reference', 'Type', 'Charges', 'Credits', 'Balance'];

        $vendor = $this->entity ? Vendor::query()->find((int) $this->entity) : null;
        if ($vendor === null) {
            return ['columns' => $columns, 'rows' => [], 'meta' => ['label' => 'Select a vendor to view their statement.']];
        }

        $r = app(VendorStatement::class)->build($vendor, (string) $this->from, (string) $this->asOf);

        $rows = [['', '', 'Opening balance', '', '', $this->peso($r['opening'])]];
        foreach ($r['rows'] as $x) {
            $rows[] = [$this->date($x['date']), (string) $x['number'], (string) $x['type'], $this->peso($x['charge']), $this->peso($x['credit']), $this->peso($x['balance'])];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => ['', '', 'BALANCE PAYABLE', '', '', $this->peso($r['closing'])],
        ];
    }
}
