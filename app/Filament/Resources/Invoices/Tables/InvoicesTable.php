<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Models\Account;
use App\Models\Invoice;
use App\Services\Fx\RecordForeignSettlement;
use App\Services\Printing\PrintInvoice;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Customer')->searchable(),
                TextColumn::make('invoice_date')->date()->sortable(),
                TextColumn::make('status')->badge()->searchable(),
                TextColumn::make('exempt_sales')->label('Exempt')->money('PHP', divideBy: 100)->sortable(),
                TextColumn::make('vatable_sales')->label('VATable')->money('PHP', divideBy: 100)->sortable(),
                TextColumn::make('vat_amount')->label('VAT')->money('PHP', divideBy: 100)->sortable(),
                TextColumn::make('total')->money('PHP', divideBy: 100)->sortable(),
            ])
            ->recordActions([
                Action::make('pdf')->label('PDF')->icon('heroicon-o-document-arrow-down')
                    ->visible(fn (Invoice $record): bool => $record->status !== InvoiceStatus::Draft)
                    ->action(fn (Invoice $record): StreamedResponse => response()->streamDownload(
                        function () use ($record): void {
                            echo app(PrintInvoice::class)->render($record);
                        },
                        ($record->number ?? 'invoice').'.pdf',
                    )),
                Action::make('settleFx')->label('Settle (FX)')->icon('heroicon-o-currency-dollar')->color('success')
                    ->visible(fn (Invoice $record): bool => $record->isForeignCurrency() && $record->outstanding() > 0)
                    ->schema([
                        TextInput::make('settlement_rate')->label('Settlement rate (PHP per 1 unit)')
                            ->numeric()->minValue(0)->required(),
                        Select::make('deposit_to_account_id')->label('Deposit to')
                            ->options(fn (): array => Account::query()->orderBy('code')->get()
                                ->mapWithKeys(fn (Account $a): array => [$a->id => "{$a->code} — {$a->name}"])->all())
                            ->searchable()->required(),
                        DatePicker::make('settlement_date')->default(now())->required(),
                    ])
                    ->action(function (Invoice $record, array $data): void {
                        try {
                            $r = app(RecordForeignSettlement::class)->handle(
                                $record,
                                (float) $data['settlement_rate'],
                                (int) $data['deposit_to_account_id'],
                                $data['settlement_date'],
                                Auth::user(),
                            );
                            $msg = $r['fx'] === 0
                                ? 'Settled (no FX difference)'
                                : ($r['fx'] > 0
                                    ? 'Settled — FX gain '.number_format($r['fx'] / 100, 2)
                                    : 'Settled — FX loss '.number_format(-$r['fx'] / 100, 2));
                            Notification::make()->success()->title($msg)->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Could not settle')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->defaultSort('invoice_date', 'desc');
    }
}
