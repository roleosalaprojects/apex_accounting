<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders the RR 7-2024 invoice layout to PDF (§12 print templates). The
 * four-total box and "VAT-EXEMPT SALE" line are driven by the stored totals.
 */
final class PrintInvoice
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['lines.taxCode', 'customer', 'company', 'preparedBy', 'checkedBy', 'approvedBy']);

        return Pdf::loadView('print.invoice', [
            'invoice' => $invoice,
            'company' => $invoice->company,
        ])->output();
    }
}
