<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Enums\AccountSubtype;
use App\Models\Account;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Chart of accounts from a CSV: matched on code; type and normal balance follow from the subtype. */
final class AccountImporter extends CompanyImporter
{
    protected static ?string $model = Account::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')->requiredMapping()->rules(['required', 'max:20'])->example('6150')
                ->guess(['account code', 'account_code', 'number']),
            ImportColumn::make('name')->requiredMapping()->rules(['required', 'max:160'])->example('Delivery expense')
                ->guess(['account', 'account name', 'title']),
            ImportColumn::make('subtype')->requiredMapping()->example('expense')
                ->rules(['required', Rule::in(array_map(fn (AccountSubtype $s): string => $s->value, AccountSubtype::cases()))])
                ->castStateUsing(fn (mixed $state): string => str_replace([' ', '-'], '_', strtolower(trim((string) $state))))
                ->guess(['type', 'account type', 'category'])
                ->helperText('One of: '.implode(', ', array_map(fn (AccountSubtype $s): string => $s->value, AccountSubtype::cases())))
                ->fillRecordUsing(function (Account $record, string $state): void {
                    $subtype = AccountSubtype::from($state);
                    $record->subtype = $subtype;
                    $record->type = $subtype->type();
                    $record->normal_balance = $subtype->normalBalance();
                }),
            ImportColumn::make('is_active')->boolean()->example('yes')->guess(['active']),
        ];
    }

    public function resolveRecord(): ?Model
    {
        $account = Account::query()->withoutGlobalScopes()
            ->where('company_id', $this->companyId())->where('code', trim((string) $this->data['code']))->first();

        return $account ?? new Account(['company_id' => $this->companyId(), 'is_system' => false, 'is_active' => true]);
    }
}
