<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reconciliations\Pages;

use App\Actions\Banking\StartReconciliation;
use App\Filament\Resources\Reconciliations\Pages\ManageReconciliation as Manage;
use App\Filament\Resources\Reconciliations\ReconciliationResource;
use App\Models\BankAccount;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ListReconciliations extends ListRecords
{
    protected static string $resource = ReconciliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start')->label('Start Reconciliation')->icon('heroicon-o-play')
                ->schema([
                    Select::make('bank_account_id')->label('Bank account')
                        ->options(fn () => BankAccount::query()->with('account')->get()
                            ->mapWithKeys(fn (BankAccount $b) => [$b->id => ($b->account?->name ?? 'Account').($b->account_no ? " ({$b->account_no})" : '')]))
                        ->required(),
                    DatePicker::make('statement_date')->default(now())->required(),
                    TextInput::make('statement_ending_balance')->label('Statement ending balance (P)')->numeric()->required(),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = Auth::user();
                    $bankAccount = BankAccount::query()->findOrFail($data['bank_account_id']);

                    try {
                        $reconciliation = app(StartReconciliation::class)->handle(
                            $bankAccount,
                            $data['statement_date'],
                            (int) round(((float) $data['statement_ending_balance']) * 100),
                            $user,
                        );
                        $this->redirect(Manage::getUrl(['record' => $reconciliation]));
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('Could not start reconciliation')->body($e->getMessage())->send();
                    }
                }),
        ];
    }
}
