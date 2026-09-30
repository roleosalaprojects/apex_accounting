<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * "Void" for a posted document (bill, payment): asks for a reason, runs the
 * given void action, and reports the outcome. Authorised through the
 * record's policy (void).
 */
final class VoidAction
{
    /**
     * @param  Closure(Model, string, User): void  $void
     * @param  Closure(Model): bool  $isOpen  whether the record can still be voided
     */
    public static function make(string $noun, Closure $void, Closure $isOpen): Action
    {
        return Action::make('void')
            ->label('Void')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->authorize('void')
            ->visible(fn (Model $record): bool => $isOpen($record))
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => 'Void '.$record->getAttribute('number'))
            ->modalDescription("This reverses the {$noun}'s entry in the ledger. The {$noun} stays on file, marked voided.")
            ->modalSubmitActionLabel('Void')
            ->schema([
                Textarea::make('reason')->required()->minLength(5),
            ])
            ->action(function (Model $record, array $data, Action $action) use ($noun, $void): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    $void($record, (string) $data['reason'], $user);
                } catch (Throwable $e) {
                    Notification::make()->danger()->title("Could not void the {$noun}")->body($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                $record->refresh();
                Notification::make()->success()->title(ucfirst($noun).' '.$record->getAttribute('number').' voided')->send();
            });
    }
}
