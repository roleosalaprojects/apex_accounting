<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankAccounts\Pages;

use App\Actions\Banking\RecordBankCharge;
use App\Actions\Banking\RecordDeposit;
use App\Actions\Banking\RecordTransfer;
use App\Data\Banking\BankChargeData;
use App\Data\Banking\DepositData;
use App\Data\Banking\TransferData;
use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Filament\Resources\BankAccounts\BankAccountResource;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ListBankAccounts extends ListRecords
{
    protected static string $resource = BankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deposit')->label('Record Deposit')->icon('heroicon-o-arrow-down-tray')
                ->schema([
                    Select::make('bank_account_id')->label('Deposit to')->options(self::cashBankOptions())->required(),
                    Select::make('source_account_id')->label('From (source)')->options(self::cashBankOptions())->required(),
                    DatePicker::make('date')->default(now())->required(),
                    TextInput::make('amount')->label('Amount (P)')->numeric()->required(),
                    TextInput::make('memo')->maxLength(255),
                ])
                ->action(fn (array $data) => self::run(fn (Company $c, User $u) => app(RecordDeposit::class)->handle(DepositData::from([
                    'company_id' => $c->id,
                    'bank_account_id' => (int) $data['bank_account_id'],
                    'source_account_id' => (int) $data['source_account_id'],
                    'date' => $data['date'],
                    'amount' => self::centavos($data['amount']),
                    'memo' => $data['memo'] ?? null,
                    'created_by' => $u->id,
                ]), $u), 'Deposit recorded')),

            Action::make('transfer')->label('Record Transfer')->icon('heroicon-o-arrows-right-left')
                ->schema([
                    Select::make('from_account_id')->label('From')->options(self::cashBankOptions())->required(),
                    Select::make('to_account_id')->label('To')->options(self::cashBankOptions())->required()->different('from_account_id'),
                    DatePicker::make('date')->default(now())->required(),
                    TextInput::make('amount')->label('Amount (P)')->numeric()->required(),
                    TextInput::make('memo')->maxLength(255),
                ])
                ->action(fn (array $data) => self::run(fn (Company $c, User $u) => app(RecordTransfer::class)->handle(TransferData::from([
                    'company_id' => $c->id,
                    'from_account_id' => (int) $data['from_account_id'],
                    'to_account_id' => (int) $data['to_account_id'],
                    'date' => $data['date'],
                    'amount' => self::centavos($data['amount']),
                    'memo' => $data['memo'] ?? null,
                    'created_by' => $u->id,
                ]), $u), 'Transfer recorded')),

            Action::make('charge')->label('Record Bank Charge')->icon('heroicon-o-receipt-percent')
                ->schema([
                    Select::make('bank_account_id')->label('Bank account')->options(self::cashBankOptions())->required(),
                    Select::make('expense_account_id')->label('Expense account')->options(self::expenseOptions())->required(),
                    DatePicker::make('date')->default(now())->required(),
                    TextInput::make('amount')->label('Amount (P)')->numeric()->required(),
                    TextInput::make('memo')->maxLength(255),
                ])
                ->action(fn (array $data) => self::run(fn (Company $c, User $u) => app(RecordBankCharge::class)->handle(BankChargeData::from([
                    'company_id' => $c->id,
                    'bank_account_id' => (int) $data['bank_account_id'],
                    'expense_account_id' => (int) $data['expense_account_id'],
                    'date' => $data['date'],
                    'amount' => self::centavos($data['amount']),
                    'memo' => $data['memo'] ?? null,
                    'created_by' => $u->id,
                ]), $u), 'Bank charge recorded')),

            CreateAction::make(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function cashBankOptions(): array
    {
        return Account::query()
            ->whereIn('subtype', [AccountSubtype::Cash->value, AccountSubtype::Bank->value])
            ->orderBy('code')->get()
            ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"])->all();
    }

    /**
     * @return array<int, string>
     */
    private static function expenseOptions(): array
    {
        return Account::query()
            ->where('type', AccountType::Expense->value)
            ->orderBy('code')->get()
            ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"])->all();
    }

    private static function centavos(mixed $pesos): int
    {
        return (int) round(((float) $pesos) * 100);
    }

    private static function run(callable $callback, string $success): void
    {
        /** @var Company $company */
        $company = Filament::getTenant();
        /** @var User $user */
        $user = Auth::user();

        try {
            $callback($company, $user);
            Notification::make()->success()->title($success)->send();
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Failed')->body($e->getMessage())->send();
        }
    }
}
