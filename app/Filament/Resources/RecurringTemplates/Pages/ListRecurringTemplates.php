<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringTemplates\Pages;

use App\Actions\Recurring\RunDueTemplates;
use App\Filament\Resources\RecurringTemplates\RecurringTemplateResource;
use App\Models\Company;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ListRecurringTemplates extends ListRecords
{
    protected static string $resource = RecurringTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runDue')->label('Run Due Templates')->icon('heroicon-o-bolt')
                ->authorize('runDue')
                ->requiresConfirmation()
                ->modalDescription('Instantiates every active template due on or before today. Each template posts as the person who last saved it.')
                ->action(function (): void {
                    /** @var Company $company */
                    $company = Filament::getTenant();
                    /** @var User $user */
                    $user = Auth::user();

                    try {
                        $runs = app(RunDueTemplates::class)->handle($company, now()->toDateString(), $user);
                        $failed = count(array_filter($runs, fn ($r): bool => $r->status === 'failed'));
                        $ok = count($runs) - $failed;

                        Notification::make()
                            ->{$failed > 0 ? 'warning' : 'success'}()
                            ->title("Ran {$ok} template(s)".($failed > 0 ? ", {$failed} failed" : ''))
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('Run failed')->body($e->getMessage())->send();
                    }
                }),
            CreateAction::make(),
        ];
    }
}
