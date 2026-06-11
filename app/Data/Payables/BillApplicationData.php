<?php

declare(strict_types=1);

namespace App\Data\Payables;

use Spatie\LaravelData\Data;

final class BillApplicationData extends Data
{
    public function __construct(
        public int $bill_id,
        public int $amount, // gross amount of the bill being settled (centavos)
    ) {}
}
