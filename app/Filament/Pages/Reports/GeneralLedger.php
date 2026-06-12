<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Models\Account;
use App\Services\Reports\GeneralLedgerReport;

class GeneralLedger extends ReportPage
{
    protected static ?string $navigationLabel = 'General Ledger';

    protected static ?int $navigationSort = 11;

    protected function entityFilter(): ?array
    {
        return [
            'label' => 'Account',
            'options' => Account::query()->orderBy('code')->get()
                ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"])->all(),
        ];
    }

    protected function payload(): array
    {
        $columns = ['Date', 'JE No.', 'Memo', 'Debit', 'Credit', 'Balance'];

        if (! $this->entity) {
            return ['columns' => $columns, 'rows' => [], 'meta' => ['label' => 'Select an account to view its ledger.']];
        }

        $r = app(GeneralLedgerReport::class)->build($this->company()->id, (int) $this->entity, (string) $this->from, (string) $this->asOf);

        $rows = [['', '', 'Opening balance', '', '', $this->peso($r['opening'])]];
        foreach ($r['rows'] as $x) {
            $rows[] = [(string) $x['date'], (string) $x['number'], (string) $x['memo'], $this->peso($x['debit']), $this->peso($x['credit']), $this->peso($x['balance'])];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => ['', '', 'ENDING BALANCE', '', '', $this->peso($r['ending'])],
        ];
    }
}
