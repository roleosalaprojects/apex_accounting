<?php

declare(strict_types=1);

namespace App\Enums;

enum TaxpayerType: string
{
    case Vat = 'vat';
    case NonVat = 'non_vat';
}
