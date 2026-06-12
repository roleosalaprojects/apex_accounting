<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reconciliations\Pages;

use App\Actions\Banking\CompleteReconciliation;
use App\Filament\Resources\Reconciliations\ReconciliationResource;
use App\Models\Reconciliation;
use App\Models\ReconciliationItem;
use App\Services\Banking\BankBalanceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Throwable;

class ManageReconciliation extends Page
{
    protected static string $resource = ReconciliationResource::class;

    protected string $view = 'filament.reconciliations.manage';

    public Reconciliation $record;

    public function mount(int|string|Reconciliation $record): void
    {
        $this->record = Reconciliation::query()
            ->with(['items.journalLine.journalEntry', 'items.journalLine.account', 'bankAccount.account'])
            ->findOrFail($record instanceof Reconciliation ? $record->getKey() : $record);
    }

    public function getTitle(): string
    {
        return 'Reconcile — '.($this->record->bankAccount?->account?->name ?? 'Bank').' @ '.$this->record->statement_date->toDateString();
    }

    public function toggle(int $itemId): void
    {
        if ($this->record->status === 'completed') {
            return;
        }

        $item = ReconciliationItem::query()
            ->where('reconciliation_id', $this->record->id)
            ->findOrFail($itemId);
        $item->forceFill(['is_cleared' => ! $item->is_cleared])->save();

        $this->record->refresh()->load(['items.journalLine.journalEntry', 'items.journalLine.account', 'bankAccount.account']);
    }

    public function clearedBalance(): int
    {
        return app(BankBalanceService::class)->clearedBalance($this->record->id);
    }

    public function difference(): int
    {
        return $this->record->statement_ending_balance->minor - $this->clearedBalance();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('complete')->label('Complete Reconciliation')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn (): bool => $this->record->status !== 'completed')
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        app(CompleteReconciliation::class)->handle($this->record);
                        $this->record->refresh();
                        Notification::make()->success()->title('Reconciliation completed')->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('Cannot complete')->body($e->getMessage())->send();
                    }
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'reconciliation' => $this->record,
            'clearedBalance' => $this->clearedBalance(),
            'difference' => $this->difference(),
        ];
    }
}
