<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Models\Customer;
use App\Services\Reports\StatementOfAccount;

class StatementOfAccountPage extends ReportPage
{
    protected static ?string $navigationLabel = 'Statement of Account';

    protected static ?int $navigationSort = 15;

    public function getTitle(): string
    {
        return 'Statement of Account';
    }

    protected function entityFilter(): ?array
    {
        return [
            'label' => 'Customer',
            'options' => Customer::query()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    protected function payload(): array
    {
        $columns = ['Date', 'Reference', 'Type', 'Charges', 'Credits', 'Balance'];

        $customer = $this->entity ? Customer::query()->find((int) $this->entity) : null;
        if ($customer === null) {
            return ['columns' => $columns, 'rows' => [], 'meta' => ['label' => 'Select a customer to view their statement.']];
        }

        $r = app(StatementOfAccount::class)->build($customer, (string) $this->from, (string) $this->asOf);

        $rows = [['', '', 'Opening balance', '', '', $this->peso($r['opening'])]];
        foreach ($r['rows'] as $x) {
            $rows[] = [(string) $x['date'], (string) $x['number'], (string) $x['type'], $this->peso($x['charge']), $this->peso($x['credit']), $this->peso($x['balance'])];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => ['', '', 'BALANCE DUE', '', '', $this->peso($r['closing'])],
        ];
    }
}
