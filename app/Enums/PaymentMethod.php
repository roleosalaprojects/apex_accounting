<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Gcash = 'gcash';
    case Maya = 'maya';
    case Check = 'check';
    case Other = 'other';
}
