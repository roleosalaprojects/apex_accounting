<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Enums\ItemType;
use App\Models\Item;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Items from a CSV: matched on SKU; accounts are given by code. */
final class ItemImporter extends CompanyImporter
{
    protected static ?string $model = Item::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('sku')->requiredMapping()->rules(['required', 'max:50'])->example('RICE-25')->label('SKU')
                ->guess(['code', 'item code', 'item_code']),
            ImportColumn::make('name')->requiredMapping()->rules(['required', 'max:160'])->example('Rice 25kg')
                ->guess(['item', 'item name', 'description']),
            ImportColumn::make('type')->requiredMapping()->example('inventory')
                ->rules(['required', Rule::in(array_map(fn (ItemType $t): string => $t->value, ItemType::cases()))])
                ->castStateUsing(fn (mixed $state): string => str_replace([' ', '-'], '_', strtolower(trim((string) $state))))
                ->helperText('inventory, non_inventory or service'),
            ImportColumn::make('unit')->example('sack')->rules(['max:30']),
            ImportColumn::make('is_vat_exempt_item')->boolean()->example('yes')->label('VAT-exempt')->guess(['vat exempt', 'exempt']),
            ImportColumn::make('default_sales_price')->example('1450')->guess(['sales price', 'selling price', 'price'])
                ->castStateUsing(fn (mixed $state): int => self::pesosToMinor($state) ?? 0),
            ImportColumn::make('default_purchase_price')->example('1310')->guess(['purchase price', 'cost'])
                ->castStateUsing(fn (mixed $state): int => self::pesosToMinor($state) ?? 0),
            ImportColumn::make('income_account')->example('4100')->guess(['income', 'sales account', 'revenue account'])
                ->fillRecordUsing(fn (Item $record, mixed $state, ItemImporter $importer): ?int => $record->income_account_id = $importer->accountId($state, 'Income account')),
            ImportColumn::make('cogs_account')->example('5100')->guess(['cogs', 'cost of sales account'])
                ->fillRecordUsing(fn (Item $record, mixed $state, ItemImporter $importer): ?int => $record->cogs_account_id = $importer->accountId($state, 'COGS account')),
            ImportColumn::make('inventory_account')->example('1300')->guess(['inventory', 'stock account'])
                ->fillRecordUsing(fn (Item $record, mixed $state, ItemImporter $importer): ?int => $record->inventory_account_id = $importer->accountId($state, 'Inventory account')),
        ];
    }

    public function resolveRecord(): ?Model
    {
        $item = Item::query()->withoutGlobalScopes()
            ->where('company_id', $this->companyId())->where('sku', trim((string) $this->data['sku']))->first();

        return $item ?? new Item(['company_id' => $this->companyId(), 'unit' => 'pc', 'is_active' => true, 'default_sales_price' => 0, 'default_purchase_price' => 0]);
    }
}
