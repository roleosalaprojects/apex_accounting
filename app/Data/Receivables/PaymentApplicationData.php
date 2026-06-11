<?php

declare(strict_types=1);

namespace App\Data\Receivables;

use Spatie\LaravelData\Data;

final class PaymentApplicationData extends Data
{
    public function __construct(
        public int $invoice_id,
        public int $amount, // centavos applied to this invoice (incl. its share of EWT)
    ) {}
}
