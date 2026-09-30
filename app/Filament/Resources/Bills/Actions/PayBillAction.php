<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bills\Actions;

use App\Actions\Payables\PayBill;
use App\Data\Payables\PayBillData;
use App\Enums\AccountSubtype;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Support\Peso;
use App\Filament\Support\PesoInput;
use App\Models\Account;
use App\Models\Bill;
use App\Models\User;
use App\Models\WithholdingCode;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Pay one bill from where it is shown (its page, the bills list): the vendor
 * and bill are fixed and the balance is filled in, so paying is one step.
 * Posts through PayBill, like the Pay Bills form.
 */
final class PayBillAction
{
    public static function make(): Action
    {
        return Action::make('pay')
            ->label('Pay')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->authorize('pay')
            ->visible(fn (Bill $record): bool => in_array($record->status, [InvoiceStatus::Posted, InvoiceStatus::PartiallyPaid], true))
            ->modalHeading(fn (Bill $record): string => "Pay {$record->number}")
            ->modalDescription(fn (Bill $record): string => $record->vendor->name.' · balance '.Peso::format($record->outstanding()))
            ->modalSubmitActionLabel('Pay')
            ->schema([
                DatePicker::make('payment_date')->default(now())->required(),
                Select::make('method')->options(PaymentMethod::class)->default(PaymentMethod::Check->value)->required(),
                Select::make('paid_from_account_id')->label('Paid from')
                    ->options(fn (): array => Account::query()
                        ->whereIn('subtype', [AccountSubtype::Cash->value, AccountSubtype::Bank->value])
                        ->orderBy('code')->get()
                        ->mapWithKeys(fn (Account $account): array => [$account->id => "{$account->code} — {$account->name}"])->all())
                    ->searchable()->required(),
                TextInput::make('external_reference_no')->label('Check no.'),
                PesoInput::make('amount')->label('Amount to settle')
                    ->default(fn (Bill $record): int => $record->outstanding())
                    ->helperText('Gross, before EWT. The cash paid out is this less the EWT withheld.')
                    ->rules(fn (Bill $record): array => ['gt:0', 'lte:'.Money::of($record->outstanding())->toDecimal()])
                    ->required(),
                Select::make('withholding_code_id')->label('EWT code')
                    ->options(fn (): array => WithholdingCode::query()->orderBy('code')->get()
                        ->mapWithKeys(fn (WithholdingCode $code): array => [$code->id => "{$code->code} — {$code->name}"])->all())
                    ->placeholder(fn (Bill $record): string => $record->vendor->defaultWithholdingCode !== null
                        ? 'Vendor default: '.$record->vendor->defaultWithholdingCode->code
                        : 'None')
                    ->helperText("Leave blank to use the vendor's default.")
                    ->searchable(),
            ])
            ->action(function (Bill $record, array $data, Action $action): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    $payment = app(PayBill::class)->handle(PayBillData::from([
                        'company_id' => $record->company_id,
                        'vendor_id' => $record->vendor_id,
                        'payment_date' => $data['payment_date'],
                        'method' => $data['method'],
                        'paid_from_account_id' => (int) $data['paid_from_account_id'],
                        'withholding_code_id' => filled($data['withholding_code_id'] ?? null) ? (int) $data['withholding_code_id'] : null,
                        'external_reference_no' => $data['external_reference_no'] ?? null,
                        'applications' => [['bill_id' => $record->id, 'amount' => (int) $data['amount']]],
                    ]), $user);
                } catch (Throwable $e) {
                    Notification::make()->danger()->title("Could not pay {$record->number}")->body($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                $record->refresh();

                Notification::make()->success()
                    ->title('Paid '.$record->vendor->name.' '.Peso::format($payment->net_paid))
                    ->body("{$payment->number}".($payment->ewt->isZero() ? '' : ' · EWT withheld '.Peso::format($payment->ewt)))
                    ->send();
            });
    }
}
