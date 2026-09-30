<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\FixedAssetRegister;

/** The fixed asset register / lapsing schedule for a period, tied to the ledger. */
class FixedAssetRegisterPage extends ReportPage
{
    protected static ?string $navigationLabel = 'Fixed Asset Register';

    protected static ?int $navigationSort = 20;

    public function getTitle(): string
    {
        return 'Fixed Asset Register (Lapsing Schedule)';
    }

    protected function payload(): array
    {
        $columns = ['No.', 'Asset', 'Category', 'Acquired', 'Cost', 'Life (mo)', 'Monthly', 'Accum. b/f', 'Charge', 'Accum. c/f', 'Net book value', 'Disposal'];
        $r = app(FixedAssetRegister::class)->build($this->company()->id, (string) $this->from, (string) $this->asOf);

        $rows = [];
        foreach ($r['rows'] as $x) {
            $disposal = $x['disposed_on'] === null ? '' : sprintf(
                '%s · proceeds %s · %s %s',
                $this->date($x['disposed_on']), $this->peso((int) $x['proceeds']),
                (int) $x['gain_loss'] >= 0 ? 'gain' : 'loss', $this->peso(abs((int) $x['gain_loss'])),
            );
            $rows[] = [
                (string) $x['number'], (string) $x['name'], (string) $x['category'], $this->date($x['acquisition_date']),
                $this->peso($x['cost']), (string) $x['life_months'], $this->peso($x['monthly']),
                $this->peso($x['accumulated_start']), $this->peso($x['depreciation']), $this->peso($x['accumulated_end']),
                $this->peso($x['net_book_value']), $disposal,
            ];
        }

        $costOff = $r['totals']['cost'] - $r['ledger']['cost'];
        $accumulatedOff = $r['totals']['accumulated_end'] - $r['ledger']['accumulated'];

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => ['', 'TOTAL', '', '', $this->peso($r['totals']['cost']), '', '', $this->peso($r['totals']['accumulated_start']), $this->peso($r['totals']['depreciation']), $this->peso($r['totals']['accumulated_end']), $this->peso($r['totals']['net_book_value']), ''],
            'meta' => [
                'ok' => $costOff === 0 && $accumulatedOff === 0,
                'label' => $costOff === 0 && $accumulatedOff === 0
                    ? 'Cost of ₱'.$this->peso($r['totals']['cost']).' and accumulated depreciation of ₱'.$this->peso($r['totals']['accumulated_end']).' tie to the ledger as of '.$this->date($this->asOf).'.'
                    : 'The register differs from the ledger as of '.$this->date($this->asOf).': cost by ₱'.$this->peso($costOff).', accumulated depreciation by ₱'.$this->peso($accumulatedOff).'.',
            ],
        ];
    }
}
