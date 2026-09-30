<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case InService = 'in_service';
    case FullyDepreciated = 'fully_depreciated';
    case Disposed = 'disposed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InService => 'In service',
            self::FullyDepreciated => 'Fully depreciated',
            self::Disposed => 'Disposed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InService => 'success',
            self::FullyDepreciated => 'info',
            self::Disposed => 'danger',
        };
    }
}
