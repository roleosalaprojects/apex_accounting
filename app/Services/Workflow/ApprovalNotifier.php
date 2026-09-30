<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Models\Company;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The bell for maker-checker: approvers hear when a document waits for
 * them, and the maker hears whether it was posted or sent back.
 */
final class ApprovalNotifier
{
    /**
     * Everyone in the company who holds the permission — except the maker.
     *
     * @return Collection<int, User>
     */
    public function approvers(Company $company, string $permission, ?int $exceptUserId = null): Collection
    {
        return $company->users()->get()
            ->filter(fn (User $user): bool => $user->id !== $exceptUserId && $user->hasCompanyPermission($company->id, $permission))
            ->values();
    }

    public function awaitingApproval(Company $company, string $permission, Model $document, string $what, string $url, ?int $makerId): void
    {
        foreach ($this->approvers($company, $permission, $makerId) as $approver) {
            Notification::make()
                ->title(ucfirst($what).' awaiting approval')
                ->body($this->describe($document, $what).' was drafted and needs a poster to approve it.')
                ->icon('heroicon-o-clipboard-document-check')->iconColor('warning')
                ->actions([Action::make('review')->label('Review')->url($url)->markAsRead()])
                ->sendToDatabase($approver);
        }
    }

    public function posted(Model $document, string $what, string $url, ?int $makerId, User $approver): void
    {
        $maker = $makerId === null ? null : User::query()->find($makerId);
        if ($maker === null || $maker->id === $approver->id) {
            return;
        }
        Notification::make()
            ->title(ucfirst($what).' posted')
            ->body($this->describe($document, $what)." was approved and posted by {$approver->name}.")
            ->icon('heroicon-o-check-circle')->iconColor('success')
            ->actions([Action::make('open')->label('Open')->url($url)->markAsRead()])
            ->sendToDatabase($maker);
    }

    public function rejected(Model $document, string $what, string $reason, ?int $makerId, User $reviewer): void
    {
        $maker = $makerId === null ? null : User::query()->find($makerId);
        if ($maker === null || $maker->id === $reviewer->id) {
            return;
        }
        Notification::make()
            ->title(ucfirst($what).' rejected')
            ->body($this->describe($document, $what)." was sent back by {$reviewer->name}: {$reason}")
            ->icon('heroicon-o-x-circle')->iconColor('danger')
            ->persistent()
            ->sendToDatabase($maker);
    }

    /** "Invoice to Golden Harvest for ₱14,500.00" — from whichever partner the document has. */
    private function describe(Model $document, string $what): string
    {
        $relation = match (true) {
            method_exists($document, 'customer') => 'customer',
            method_exists($document, 'vendor') => 'vendor',
            default => null,
        };
        $party = $relation === null ? null : $document->getRelationValue($relation)?->getAttribute('name');
        $total = $document->getAttribute('total');
        $amount = $total instanceof Money ? ' for '.$total->format() : '';

        return ucfirst($what).($party ? " to {$party}" : '').$amount;
    }
}
