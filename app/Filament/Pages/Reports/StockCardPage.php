<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Models\Item;
use App\Services\Reports\StockCardReport;
use App\Support\Quantity;

/** One item's movements over a period with running balances; opened preselected from the item's page (?item=). */
class StockCardPage extends ReportPage
{
    protected static ?string $navigationLabel = 'Stock Card';

    protected static ?int $navigationSort = 19;

    public function getTitle(): string
    {
        return 'Stock Card';
    }

    public function mount(): void
    {
        parent::mount();

        $item = request()->integer('item');
        if ($item > 0 && Item::query()->whereKey($item)->exists()) {
            $this->entity = (string) $item;
        }
    }

    protected function entityFilter(): ?array
    {
        return [
            'label' => 'Item',
            'options' => Item::query()->where('type', 'inventory')->orderBy('sku')->get()
                ->mapWithKeys(fn (Item $item): array => [$item->id => "{$item->sku} — {$item->name}"])->all(),
        ];
    }

    protected function payload(): array
    {
        $columns = ['Date', 'Movement', 'Reference', 'Description', 'In', 'Out', 'Unit cost', 'Value', 'Balance qty', 'Balance value'];

        $item = $this->entity ? Item::query()->find((int) $this->entity) : null;
        if ($item === null) {
            return ['columns' => $columns, 'rows' => [], 'meta' => ['label' => 'Select an item to view its stock card.']];
        }

        $r = app(StockCardReport::class)->build($item, (string) $this->from, (string) $this->asOf);

        $rows = [['', 'Opening balance', '', '', '', '', '', '', Quantity::compact($r['opening']['qty_units']), $this->peso($r['opening']['value'])]];
        foreach ($r['rows'] as $x) {
            $rows[] = [
                $this->date($x['date']), (string) $x['kind'], (string) ($x['reference'] ?? ''), (string) ($x['description'] ?? ''),
                $x['in_units'] > 0 ? Quantity::compact($x['in_units']) : '',
                $x['out_units'] > 0 ? Quantity::compact($x['out_units']) : '',
                $this->peso($x['unit_cost']), $this->peso($x['value']),
                Quantity::compact($x['balance_units']), $this->peso($x['balance_value']),
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => ['', 'CLOSING BALANCE', '', '', '', '', '', '', Quantity::compact($r['closing']['qty_units']), $this->peso($r['closing']['value'])],
            'meta' => ['label' => "{$item->sku} — {$item->name}, per {$item->unit}, at weighted-average cost."],
        ];
    }
}
