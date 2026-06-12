<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets\Pages;

use App\Actions\Assets\RunMonthlyDepreciation;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runDepreciation')->label('Run Depreciation')->icon('heroicon-o-calculator')
                ->schema([
                    Select::make('period_id')->label('Period')
                        ->options(fn () => AccountingPeriod::query()
                            ->where('status', 'open')
                            ->orderBy('fiscal_year')->orderBy('period_no')->get()
                            ->mapWithKeys(fn (AccountingPeriod $p) => [
                                $p->id => "{$p->fiscal_year}-".str_pad((string) $p->period_no, 2, '0', STR_PAD_LEFT)
                                    ." ({$p->starts_on->toDateString()} – {$p->ends_on->toDateString()})",
                            ]))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var Company $company */
                    $company = Filament::getTenant();
                    /** @var User $user */
                    $user = Auth::user();
                    $period = AccountingPeriod::query()->findOrFail($data['period_id']);

                    try {
                        $entries = app(RunMonthlyDepreciation::class)->handle($company, $period, $user);
                        Notification::make()->success()
                            ->title('Depreciation run complete')
                            ->body(count($entries).' journal entr'.(count($entries) === 1 ? 'y' : 'ies').' posted.')
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('Depreciation run failed')->body($e->getMessage())->send();
                    }
                }),
            CreateAction::make(),
        ];
    }
}
