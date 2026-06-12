<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Models\VendorPayment;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders the BIR Form 2307 (Certificate of Creditable Tax Withheld at Source)
 * for a vendor payment's withholding transactions (§7).
 */
final class Print2307
{
    public function render(VendorPayment $payment): string
    {
        $payment->loadMissing(['vendor', 'company', 'withholdingTransactions.withholdingCode']);

        return Pdf::loadView('print.form2307', [
            'payment' => $payment,
            'company' => $payment->company,
            'vendor' => $payment->vendor,
        ])->output();
    }
}
