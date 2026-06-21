<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Company;
use App\Services\Reports\EwtSummaryReport;

/**
 * Expanded-withholding alphalist export (QAP / 1604-E schedule): one line per
 * payee (vendor) and ATC with the income payment and tax withheld, in a
 * pipe-delimited, BIR-validation-style flat file (H header / D detail / C
 * control). Amounts tie to the EWT Summary.
 *
 * NOTE: the exact BIR alphalist/DAT layout is version-specific and should be
 * validated against the current BIR validation module before official filing.
 */
final class AlphalistExporter
{
    public function __construct(private readonly EwtSummaryReport $ewt) {}

    public function ewt(Company $company, string $from, string $to): string
    {
        $data = $this->ewt->build($company->id, $from, $to);
        $peso = static fn (int $minor): string => number_format($minor / 100, 2, '.', '');

        $lines = [];
        $lines[] = implode('|', ['H', 'QAP', $company->tin ?? '', $company->branch_code, $company->name, $from, $to]);

        foreach ($data['rows'] as $r) {
            $lines[] = implode('|', [
                'D',
                (string) ($r['tin'] ?? ''),
                (string) ($r['vendor'] ?? ''),
                (string) ($r['atc'] ?? ''),
                number_format((int) $r['rate_bp'] / 100, 2, '.', ''), // withholding rate %
                $peso((int) $r['base']),
                $peso((int) $r['ewt']),
            ]);
        }

        $lines[] = implode('|', ['C', (string) count($data['rows']), $peso($data['total_base']), $peso($data['total_ewt'])]);

        return implode("\r\n", $lines)."\r\n";
    }
}
