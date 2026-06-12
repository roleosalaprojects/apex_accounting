<?php

declare(strict_types=1);

namespace App\Filament\Pages\Tenancy;

use App\Actions\Ledger\OpenFiscalYear;
use App\Actions\Ledger\SetupNewCompany;
use App\Enums\CompanyRole;
use App\Enums\TaxpayerType;
use App\Models\Company;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Tenant onboarding (§3): creates the company, attaches the creator as Owner,
 * seeds the BIR chart of accounts / tax codes / sequences via SetupNewCompany,
 * and opens the current fiscal year.
 */
class RegisterCompany extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register company';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(160),
                TextInput::make('tin')->label('TIN')->maxLength(20),
                TextInput::make('branch_code')->default('00000')->maxLength(5),
                Select::make('taxpayer_type')
                    ->options(TaxpayerType::class)
                    ->default(TaxpayerType::Vat->value)
                    ->required(),
                TextInput::make('fiscal_year_start_month')
                    ->label('Fiscal year starts in month')
                    ->numeric()->integer()->minValue(1)->maxValue(12)->default(1)
                    ->required(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(array $data): Company
    {
        /** @var User $user */
        $user = Auth::user();

        return DB::transaction(function () use ($data, $user): Company {
            /** @var Company $company */
            $company = Company::query()->create($data);
            $company->users()->attach($user->id, ['role' => CompanyRole::Owner->value]);

            app(SetupNewCompany::class)->handle($company);
            app(OpenFiscalYear::class)->handle($company, (int) now()->year);

            return $company;
        });
    }
}
