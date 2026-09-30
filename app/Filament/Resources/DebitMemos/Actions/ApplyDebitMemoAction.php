<?php

declare(strict_types=1);

namespace App\Filament\Resources\DebitMemos\Actions;

use App\Actions\Payables\ApplyDebitMemo;
use App\Enums\InvoiceStatus;
use App\Models\Bill;
use App\Models\DebitMemo;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Throwable;

/** Settle the vendor's open bills with what is left on the memo. */
final class ApplyDebitMemoAction
{
    public static function make(): Action
    {
        return Action::make('apply')
            ->label('Apply to bills')
            ->icon('heroicon-o-arrow-down-on-square-stack')
            ->authorize('apply')
            ->visible(fn (DebitMemo $record): bool => in_array($record->status, ['posted', 'applied'], true)
                && $record->total->minor > (int) $record->applications()->sum('amount'))
            ->schema(fn (DebitMemo $record): array => [
                Repeater::make('applications')
                    ->label('Apply to')
                    ->minItems(1)
                    ->defaultItems(1)
                    ->columns(2)
                    ->schema([
                        Select::make('bill_id')
                            ->label('Bill')
                            ->options(fn () => Bill::query()
                                ->where('vendor_id', $record->vendor_id)
                                ->whereIn('status', [InvoiceStatus::Posted->value, InvoiceStatus::PartiallyPaid->value])
                                ->orderBy('bill_date')
                                ->get()
                                ->mapWithKeys(fn (Bill $b) => [
                                    $b->id => "{$b->number} — ₱".number_format($b->outstanding() / 100, 2).' open',
                                ]))
                            ->required()->searchable(),
                        TextInput::make('amount')
                            ->label('Amount (₱)')
                            ->numeric()
                            ->default(fn (): string => number_format(($record->total->minor - (int) $record->applications()->sum('amount')) / 100, 2, '.', ''))
                            ->required(),
                    ]),
            ])
            ->action(function (DebitMemo $record, array $data): void {
                $applications = array_map(fn (array $a): array => [
                    'bill_id' => (int) $a['bill_id'],
                    'amount' => (int) round(((float) $a['amount']) * 100),
                ], $data['applications']);
                try {
                    app(ApplyDebitMemo::class)->handle($record, $applications);
                    Notification::make()->success()->title('Debit memo applied')->send();
                } catch (Throwable $e) {
                    Notification::make()->danger()->title('Could not apply debit memo')->body($e->getMessage())->send();
                }
            });
    }
}
