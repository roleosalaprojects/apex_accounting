<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Models\Customer;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Model;

/** Customers from a CSV: matched on code, so a second import updates. */
final class CustomerImporter extends CompanyImporter
{
    protected static ?string $model = Customer::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')->requiredMapping()->rules(['required', 'max:50'])->example('CUST-001')
                ->guess(['customer_code', 'customer code', 'id']),
            ImportColumn::make('name')->requiredMapping()->rules(['required', 'max:160'])->example('Golden Harvest Grocery')
                ->guess(['customer', 'customer_name', 'company']),
            ImportColumn::make('tin')->rules(['max:20'])->example('123-456-789-00000')->label('TIN'),
            ImportColumn::make('address')->example('Unit 2, Burgos Ave., Cabanatuan City'),
            ImportColumn::make('email')->rules(['nullable', 'email'])->example('ap@goldenharvest.ph'),
            ImportColumn::make('contact_person')->example('Maria Santos')->guess(['contact', 'contact person']),
            ImportColumn::make('terms_days')->integer()->rules(['nullable', 'integer', 'min:0', 'max:365'])->example('30')
                ->guess(['terms', 'payment terms', 'credit terms']),
            ImportColumn::make('credit_limit')->example('150000')->guess(['credit limit', 'limit'])
                ->castStateUsing(fn (mixed $state): ?int => self::pesosToMinor($state)),
            ImportColumn::make('is_withholding_agent')->boolean()->example('no')->guess(['withholding agent', 'ewt agent']),
        ];
    }

    public function resolveRecord(): ?Model
    {
        $customer = Customer::query()->withoutGlobalScopes()
            ->where('company_id', $this->companyId())->where('code', trim((string) $this->data['code']))->first();

        return $customer ?? new Customer(['company_id' => $this->companyId(), 'terms_days' => 0]);
    }
}
