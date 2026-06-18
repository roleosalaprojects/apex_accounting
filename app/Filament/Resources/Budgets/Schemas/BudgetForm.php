<?php

declare(strict_types=1);

namespace App\Filament\Resources\Budgets\Schemas;

use App\Models\Account;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BudgetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('fiscal_year')
                    ->numeric()->integer()->minValue(2000)->maxValue(2100)
                    ->default((int) date('Y'))
                    ->required(),
                TextInput::make('name')->required()->maxLength(160),
                Select::make('status')
                    ->options(['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'])
                    ->default('active')->required(),
                Textarea::make('notes')->rows(2)->columnSpanFull(),
                Repeater::make('lines')
                    ->relationship()
                    ->label('Budget lines (annual targets per account)')
                    ->schema([
                        Select::make('account_id')
                            ->label('Account')
                            ->options(fn (): array => Account::query()->orderBy('code')->get()
                                ->mapWithKeys(fn (Account $a): array => [$a->id => "{$a->code} — {$a->name}"])->all())
                            ->searchable()->required(),
                        TextInput::make('amount')
                            ->label('Annual budget (₱)')
                            ->numeric()->required()
                            ->formatStateUsing(fn (?int $state): ?float => $state !== null ? $state / 100 : null)
                            ->dehydrateStateUsing(fn (mixed $state): int => (int) round((float) $state * 100)),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Add account'),
            ]);
    }
}
