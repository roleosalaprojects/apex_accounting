<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Models\Vendor;
use App\Models\WithholdingCode;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Model;

/** Vendors from a CSV: matched on code; the default EWT code is looked up by its code. */
final class VendorImporter extends CompanyImporter
{
    protected static ?string $model = Vendor::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')->requiredMapping()->rules(['required', 'max:50'])->example('VEND-001')
                ->guess(['vendor_code', 'vendor code', 'supplier code', 'id']),
            ImportColumn::make('name')->requiredMapping()->rules(['required', 'max:160'])->example('Rice Trader Inc.')
                ->guess(['vendor', 'supplier', 'vendor_name', 'supplier_name']),
            ImportColumn::make('tin')->rules(['max:20'])->example('987-654-321-00000')->label('TIN'),
            ImportColumn::make('address')->example('Km 5 Maharlika Highway, Cabanatuan City'),
            ImportColumn::make('email')->rules(['nullable', 'email'])->example('sales@ricetrader.ph'),
            ImportColumn::make('contact_person')->example('Jose Reyes')->guess(['contact', 'contact person']),
            ImportColumn::make('terms_days')->integer()->rules(['nullable', 'integer', 'min:0', 'max:365'])->example('30')
                ->guess(['terms', 'payment terms']),
            ImportColumn::make('is_vat_registered')->boolean()->example('yes')->guess(['vat registered', 'vat']),
            ImportColumn::make('default_withholding_code')->example('WC158')->label('Default EWT code')
                ->guess(['ewt code', 'atc', 'withholding code'])
                ->fillRecordUsing(function (Vendor $record, mixed $state): void {
                    if (blank($state)) {
                        $record->default_withholding_code_id = null;

                        return;
                    }
                    $id = WithholdingCode::query()->withoutGlobalScopes()
                        ->where('company_id', $record->company_id)->where('code', trim((string) $state))->value('id');
                    if ($id === null) {
                        throw new RowImportFailedException("No EWT code '{$state}' — add it under Settings → EWT / ATC Codes first.");
                    }
                    $record->default_withholding_code_id = (int) $id;
                }),
        ];
    }

    public function resolveRecord(): ?Model
    {
        $vendor = Vendor::query()->withoutGlobalScopes()
            ->where('company_id', $this->companyId())->where('code', trim((string) $this->data['code']))->first();

        return $vendor ?? new Vendor(['company_id' => $this->companyId(), 'terms_days' => 0, 'is_vat_registered' => false]);
    }
}
