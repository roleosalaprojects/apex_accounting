<?php

declare(strict_types=1);

namespace App\Filament\Resources\DebitMemos\Tables;

use App\Filament\Resources\DebitMemos\Actions\ApplyDebitMemoAction;
use App\Filament\Support\Peso;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DebitMemosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('vendor.name')->label('Vendor')->searchable(),
                TextColumn::make('memo_date')->date()->sortable(),
                TextColumn::make('memo')->label('Reason')->limit(40)->placeholder('—')->toggleable(),
                TextColumn::make('total')->formatStateUsing(fn (mixed $state): ?string => Peso::state($state))->alignEnd(),
                TextColumn::make('applications_sum_amount')->sum('applications', 'amount')
                    ->label('Applied')->formatStateUsing(fn (mixed $state): ?string => Peso::state($state))->alignEnd()->placeholder('—'),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'applied' => 'success',
                        'posted' => 'info',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                ApplyDebitMemoAction::make(),
            ])
            ->defaultSort('memo_date', 'desc');
    }
}
