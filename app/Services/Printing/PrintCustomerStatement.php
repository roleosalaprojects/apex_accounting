<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Models\Customer;
use App\Services\Reports\ArAgingReport;
use App\Services\Reports\StatementOfAccount;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** A customer's Statement of Account for a period, with the open balance aged. */
final class PrintCustomerStatement
{
    public function __construct(
        private readonly StatementOfAccount $statement,
        private readonly ArAgingReport $aging,
    ) {}

    public function render(Customer $customer, string $from, string $asOf): string
    {
        return Pdf::loadView('print.statement', $this->data($customer, $from, $asOf))->output();
    }

    /**
     * @return array<string, mixed>
     */
    public function data(Customer $customer, string $from, string $asOf): array
    {
        $customer->loadMissing('company');
        $soa = $this->statement->build($customer, $from, $asOf);

        $invoiceIds = DB::table('invoices')->where('customer_id', $customer->id)->pluck('id')->all();
        $buckets = array_fill_keys(['Current', '1–30 days', '31–60 days', '61–90 days', 'Over 90 days'], 0);
        $labels = ['current' => 'Current', '1_30' => '1–30 days', '31_60' => '31–60 days', '61_90' => '61–90 days', '90_plus' => 'Over 90 days'];
        foreach ($this->aging->build($customer->company_id, $asOf)['rows'] as $row) {
            if (in_array($row['invoice_id'], $invoiceIds, true)) {
                $buckets[$labels[$row['bucket']]] += $row['outstanding'];
            }
        }
        $buckets['Total'] = array_sum($buckets);

        $peso = fn (int $minor): string => Money::of($minor)->format();

        return [
            'customer' => $customer,
            'company' => $customer->company,
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
