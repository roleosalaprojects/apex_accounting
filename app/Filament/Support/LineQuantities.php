<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Quantity;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One quantity box per open order line, for the Deliver / Invoice / Bill
 * modals: labelled with the line and how much of it is still open, capped
 * at that, and read back as [line id => qty].
 */
final class LineQuantities
{
    /**
     * @param  Collection<int, Model>  $lines
     * @param  callable(Model): int  $openUnits
     * @param  array<int, string>  $defaults  line id => qty
     * @return list<TextInput>
     */
    public static function inputs(Collection $lines, callable $openUnits, array $defaults, string $verb): array
    {
        $inputs = [];
        foreach ($lines as $line) {
            $open = $openUnits($line);
            if ($open <= 0) {
                continue;
            }
            $inputs[] = TextInput::make('qty.'.$line->getKey())
                ->label($line->getAttribute('description'))
                ->helperText(Quantity::compact($open).' of '.Quantity::compact(Quantity::toUnits($line->getAttribute('qty')))." left {$verb}")
                ->numeric()->minValue(0)->maxValue((float) Quantity::compact($open))
                ->default($defaults[$line->getKey()] ?? null);
        }

        return $inputs;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public static function read(array $data): array
    {
        $quantities = [];
        foreach ($data['qty'] ?? [] as $lineId => $qty) {
            if (filled($qty) && (float) $qty > 0) {
                $quantities[(int) $lineId] = (string) $qty;
            }
        }

        return $quantities;
    }
}
