<?php

declare(strict_types=1);

namespace App\Enums;

enum AssetStatus: string
{
    case Draft = 'draft';
    case InService = 'in_service';
    case FullyDepreciated = 'fully_depreciated';
    case Disposed = 'disposed';
}
