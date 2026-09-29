<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items\RelationManagers;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Bills\BillResource;
use App\Filament\Support\Peso;
use App\Filament\Support\Qty;
use App\Models\Bill;
use App\Models\BillLine;
use App\Support\Money;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Posted bill lines for the item, newest first. */
class PurchasesRelationManager extends RelationManager
{
    protected static string $relationship = 'billLines';

    protected static ?string $title = 'Purchases';

    /** Lines have no policy of their own: whoever may list bills may see them. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, ViewRecord::class)
            && (Filament::auth()->user()?->can('viewAny', Bill::class) ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select('bill_lines.*')
                ->join('bills', 'bills.id', '=', 'bill_lines.bill_id')
                ->whereIn('bills.status', [InvoiceStatus::Posted->value, InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Paid->value])
                ->with('bill.vendor')
                ->orderByDesc('bills.bill_date')
                ->orderByDesc('bill_lines.id'))
            ->columns([
                TextColumn::make('bill.bill_date')->label('Date')->date('M j, Y'),
                TextColumn::make('bill.number')->label('Bill'),
                TextColumn::make('bill.vendor.name')->label('Vendor'),
                TextColumn::make('qty')->label('Qty')->alignEnd()
                    ->formatStateUsing(fn (string|int|float $state): string => Qty::decimal($state)),
                TextColumn::make('unit_price')->label('Unit cost')->alignEnd()
                    ->formatStateUsing(fn (Money $state): string => Peso::format($state)),
                TextColumn::make('line_total')->label('Amount')->alignEnd()
                    ->formatStateUsing(fn (Money $state): string => Peso::format($state)),
            ])
            ->recordUrl(fn (BillLine $record): string => BillResource::getUrl('view', ['record' => $record->bill_id]));
    }
}
