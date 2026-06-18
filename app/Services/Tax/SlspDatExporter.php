<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Company;
use App\Services\Reports\PurchaseBook;
use App\Services\Reports\SalesBook;

/**
 * Summary List of Sales / Purchases (SLSP) export. Groups the Sales/Purchase
 * books by partner TIN and emits a pipe-delimited, RELIEF-style flat file
 * (H header / D detail-per-partner / C control footer).
 *
 * NOTE: amounts are correct and tie to the books; the exact BIR RELIEF/DAT
 * layout is version-specific and should be validated against the current BIR
 * validation module before official submission.
 */
final class SlspDatExporter
{
    public function __construct(
        private readonly SalesBook $salesBook,
        private readonly PurchaseBook $purchaseBook,
    ) {}

    public function sales(Company $company, string $from, string $to): string
    {
        $rows = $this->salesBook->build($company->id, $from, $to)['rows'];

        $detail = array_map(fn (array $r): array => [
            'tin' => $r['tin'],
            'name' => $r['customer'],
            'vatable' => (int) $r['vatable'],
            'exempt' => (int) $r['exempt'],
            'zero_rated' => (int) $r['zero_rated'],
            'vat' => (int) $r['output_vat'],
        ], $rows);

        return $this->render($company, 'S', $from, $to, $this->group($detail));
    }

    public function purchases(Company $company, string $from, string $to): string
    {
        $rows = $this->purchaseBook->build($company->id, $from, $to)['rows'];

        $detail = array_map(fn (array $r): array => [
            'tin' => $r['tin'],
            'name' => $r['vendor'],
            'vatable' => (int) $r['vatable'],
            'exempt' => (int) $r['exempt'],
            'zero_rated' => 0,
            'vat' => (int) $r['input_vat_direct'] + (int) $r['input_vat_common'],
        ], $rows);

        return $this->render($company, 'P', $from, $to, $this->group($detail));
    }

    /**
     * Sum the per-document rows into one line per partner (TIN + name).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function group(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $r) {
            $key = ($r['tin'] ?? '').'|'.($r['name'] ?? '');
            $grouped[$key] ??= [
                'tin' => $r['tin'] ?? '',
                'name' => $r['name'] ?? '',
                'vatable' => 0, 'exempt' => 0, 'zero_rated' => 0, 'vat' => 0,
            ];
            foreach (['vatable', 'exempt', 'zero_rated', 'vat'] as $f) {
                $grouped[$key][$f] += (int) $r[$f];
            }
        }

        $list = array_values($grouped);
        usort($list, fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        return $list;
    }

    /**
     * @param  list<array<string, mixed>>  $partners
     */
    private function render(Company $company, string $kind, string $from, string $to, array $partners): string
    {
        $peso = static fn (int $minor): string => number_format($minor / 100, 2, '.', '');

        $lines = [];
        $lines[] = implode('|', ['H', $kind, $company->tin ?? '', $company->branch_code, $company->name, $from, $to]);

        $totals = ['vatable' => 0, 'exempt' => 0, 'zero_rated' => 0, 'vat' => 0];
        foreach ($partners as $p) {
            $lines[] = implode('|', [
                'D',
                $p['tin'],
                $p['name'],
                $peso((int) $p['vatable']),
                $peso((int) $p['exempt']),
                $peso((int) $p['zero_rated']),
                $peso((int) $p['vat']),
            ]);
            foreach ($totals as $f => $_) {
                $totals[$f] += (int) $p[$f];
            }
        }

        $lines[] = implode('|', [
            'C',
            (string) count($partners),
            $peso($totals['vatable']),
            $peso($totals['exempt']),
            $peso($totals['zero_rated']),
            $peso($totals['vat']),
        ]);

        return implode("\r\n", $lines)."\r\n";
    }
}
