<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerPayments;

use App\Filament\Resources\CustomerPayments\Pages\CreateCustomerPayment;
use App\Filament\Resources\CustomerPayments\Pages\ListCustomerPayments;
use App\Filament\Resources\CustomerPayments\Schemas\CustomerPaymentForm;
use App\Filament\Resources\CustomerPayments\Tables\CustomerPaymentsTable;
use App\Models\CustomerPayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CustomerPaymentResource extends Resource
{
    protected static ?string $model = CustomerPayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Receive Payments';

    protected static string|\UnitEnum|null $navigationGroup = 'Receivables';

    public static function form(Schema $schema): Schema
    {
        return CustomerPaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerPaymentsTable::configure($table);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomerPayments::route('/'),
            'create' => CreateCustomerPayment::route('/create'),
        ];
    }
}
