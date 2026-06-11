<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Input VAT three-bucket attribution (§5.3).
 */
enum VatBucket: string
{
    case DirectVatable = 'direct_vatable'; // 100% creditable -> Dr 1400 Input VAT
    case DirectExempt = 'direct_exempt';   // not creditable -> capitalized into cost
    case Common = 'common';                // overhead -> Dr 1410, allocated quarterly
}
