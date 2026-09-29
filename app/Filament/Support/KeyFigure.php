<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

/** A headline number at the top of a view page, with a short note under it. */
final class KeyFigure
{
    public static function make(string $name, string $label): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->size(TextSize::Large)
            ->weight(FontWeight::SemiBold);
    }
}
