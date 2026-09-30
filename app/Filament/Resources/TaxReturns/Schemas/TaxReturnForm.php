<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaxReturns\Schemas;

use App\Enums\TaxReturnType;
use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class TaxReturnForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(fn (): array => collect(TaxReturnType::cases())
                        ->mapWithKeys(fn (TaxReturnType $t): array => [$t->value => $t->label()])->all())
                    ->default(TaxReturnType::Vat2550Q->value)
                    ->live()
                    ->required(),
                TextInput::make('fiscal_year')
                    ->numeric()->integer()->minValue(2000)->maxValue(2100)
                    ->default((int) date('Y'))
                    ->required(),
                Select::make('quarter')
                    ->options([1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4'])
                    ->default(1)
                    ->visible(fn (Get $get): bool => self::period($get) === 'quarter')
                    ->required(fn (Get $get): bool => self::period($get) === 'quarter'),
                Select::make('month')
                    ->options(self::monthOptions(...))
                    ->helperText('The 0619-E covers the first two months of a quarter; the third month is reported on the 1601-EQ.')
                    ->visible(fn (Get $get): bool => self::period($get) === 'month')
                    ->required(fn (Get $get): bool => self::period($get) === 'month'),
            ]);
    }

    private static function period(Get $get): string
    {
        return TaxReturnType::tryFrom((string) $get('type'))?->period() ?? 'quarter';
    }

    /**
     * The months a 0619-E can cover: every month but the last of each fiscal quarter.
     *
     * @return array<int, string>
     */
    private static function monthOptions(): array
    {
        /** @var Company|null $company */
        $company = Filament::getTenant();
        $startMonth = $company?->fiscal_year_start_month ?? 1;

        $options = [];
        for ($offset = 0; $offset < 12; $offset++) {
            if ($offset % 3 === 2) {
                continue;
            }
            $month = ($startMonth - 1 + $offset) % 12 + 1;
            $options[$month] = Carbon::create(2000, $month, 1)->format('F');
        }

        return $options;
    }
}
