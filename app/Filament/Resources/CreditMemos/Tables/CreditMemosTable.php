<?php

declare(strict_types=1);

namespace App\Filament\Resources\CreditMemos\Tables;

use App\Actions\Receivables\ApplyCreditMemo;
use App\Enums\InvoiceStatus;
use App\Models\CreditMemo;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

class CreditMemosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Customer')->searchable(),
                TextColumn::make('memo_date')->date()->sortable(),
                TextColumn::make('total')->money('PHP', divideBy: 100),
                TextColumn::make('applications_sum_amount')->sum('applications', 'amount')
                    ->label('Applied')->money('PHP', divideBy: 100)->placeholder('—'),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'applied' => 'success',
                        'posted' => 'info',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('apply')
                    ->label('Apply to invoices')
                    ->icon('heroicon-o-arrow-down-on-square-stack')
                    ->visible(fn (CreditMemo $record): bool => in_array($record->status, ['posted', 'applied'], true)
                        && $record->total->minor > (int) $record->applications()->sum('amount'))
                    ->schema(fn (CreditMemo $record): array => [
                        Repeater::make('applications')
                            ->label('Apply to')
                            ->minItems(1)
                            ->defaultItems(1)
                            ->columns(2)
                            ->schema([
                                Select::make('invoice_id')
                                    ->label('Invoice')
                                    ->options(fn () => Invoice::query()
                                        ->where('customer_id', $record->customer_id)
                                        ->whereIn('status', [InvoiceStatus::Posted->value, InvoiceStatus::PartiallyPaid->value])
                                        ->orderBy('invoice_date')
                                        ->get()
                                        ->mapWithKeys(fn (Invoice $i) => [
                                            $i->id => "{$i->number} — ₱".number_format($i->outstanding() / 100, 2).' open',
                                        ]))
                                    ->required(),
                                TextInput::make('amount')
                                    ->label('Amount (₱)')
                                    ->numeric()
                                    ->required(),
                            ]),
                    ])
                    ->action(function (CreditMemo $record, array $data): void {
                        $applications = array_map(fn (array $a): array => [
                            'invoice_id' => (int) $a['invoice_id'],
                            'amount' => (int) round(((float) $a['amount']) * 100),
                        ], $data['applications']);

                        try {
                            app(ApplyCreditMemo::class)->handle($record, $applications);
                            Notification::make()->success()->title('Credit applied')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Could not apply credit')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->defaultSort('memo_date', 'desc');
    }
}
