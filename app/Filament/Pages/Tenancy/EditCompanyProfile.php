<?php

declare(strict_types=1);

namespace App\Filament\Pages\Tenancy;

use App\Actions\Admin\ExportCompanyData;
use App\Enums\TaxpayerType;
use App\Models\Company;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Company settings (§3): registration details, posting controls, and the
 * owner-gated full data export (ExportCompanyData).
 */
class EditCompanyProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Company settings';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(160),
                TextInput::make('tin')->label('TIN')->maxLength(20),
                TextInput::make('branch_code')->maxLength(5),
                Select::make('taxpayer_type')->options(TaxpayerType::class)->required(),
                TextInput::make('fiscal_year_start_month')
                    ->label('Fiscal year starts in month')
                    ->numeric()->integer()->minValue(1)->maxValue(12)->required(),
                Toggle::make('require_approval')
                    ->label('Require approval before posting')
                    ->helperText('Drafts must be approved by an Owner or Accountant before they post.'),
                Toggle::make('block_negative_inventory')
                    ->label('Block negative inventory'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportData')
                ->label('Export All Data')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->visible(function (): bool {
                    /** @var Company|null $company */
                    $company = Filament::getTenant();
                    /** @var User|null $user */
                    $user = Auth::user();

                    return $company !== null && $user?->roleIn($company->id)?->canManageCompany() === true;
                })
                ->requiresConfirmation()
                ->modalDescription('Downloads a ZIP of every table for this company as CSV, plus a manifest. The export is recorded in the audit log.')
                ->action(function () {
                    /** @var Company $company */
                    $company = Filament::getTenant();
                    /** @var User $user */
                    $user = Auth::user();

                    try {
                        $path = app(ExportCompanyData::class)->handle($company, $user);

                        return response()
                            ->download($path, str($company->name)->slug().'-export-'.now()->toDateString().'.zip')
                            ->deleteFileAfterSend();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('Export failed')->body($e->getMessage())->send();

                        return null;
                    }
                }),
        ];
    }
}
