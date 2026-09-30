<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Models\Vendor;
use App\Services\Reports\ApAgingReport;
use App\Services\Reports\VendorStatement;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** A vendor's statement for a period, with what we still owe them aged — for reconciling against theirs. */
final class PrintVendorStatement
{
    public function __construct(
        private readonly VendorStatement $statement,
        private readonly ApAgingReport $aging,
    ) {}

    public function render(Vendor $vendor, string $from, string $asOf): string
    {
        return Pdf::loadView('print.statement', $this->data($vendor, $from, $asOf))->output();
    }

    /**
     * @return array<string, mixed>
     */
    public function data(Vendor $vendor, string $from, string $asOf): array
    {
        $vendor->loadMissing('company');
        $soa = $this->statement->build($vendor, $from, $asOf);

        $billIds = DB::table('bills')->where('vendor_id', $vendor->id)->pluck('id')->all();
        $buckets = array_fill_keys(['Current', '1–30 days', '31–60 days', '61–90 days', 'Over 90 days'], 0);
        $labels = ['current' => 'Current', '1_30' => '1–30 days', '31_60' => '31–60 days', '61_90' => '61–90 days', '90_plus' => 'Over 90 days'];
        foreach ($this->aging->build($vendor->company_id, $asOf)['rows'] as $row) {
            if (in_array($row['bill_id'], $billIds, true)) {
                $buckets[$labels[$row['bucket']]] += $row['outstanding'];
            }
        }
        $buckets['Total'] = array_sum($buckets);

        $peso = fn (int $minor): string => Money::of($minor)->format();

        return [
            'title' => 'VENDOR STATEMENT',
            'partyLabel' => 'Vendor',
            'party' => $vendor,
            'balanceLabel' => 'Balance payable',
            'closingLabel' => 'Balance payable',
            'note' => 'Amounts are in Philippine pesos, as recorded in our books. Please let us know of any difference against your records.',
            'company' => $vendor->company,
            'from' => Carbon::parse($from)->format('M j, Y'),
            'asOf' => Carbon::parse($asOf)->format('M j, Y'),
            'opening' => $peso($soa['opening']),
            'closing' => $peso($soa['closing']),
            'rows' => array_map(fn (array $row): array => [
                'date' => Carbon::parse($row['date'])->format('M j, Y'),
                'number' => $row['number'],
                'type' => $row['type'],
                'charge' => $row['charge'] > 0 ? $peso($row['charge']) : '',
                'credit' => $row['credit'] > 0 ? $peso($row['credit']) : '',
                'balance' => $peso($row['balance']),
            ], $soa['rows']),
            'aging' => array_map($peso, $buckets),
        ];
    }
}
