<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalEntries\Schemas;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Fund;
use App\Models\Project;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Manual journal entry form. Posted via CreateJournalEntry -> PostJournalEntry
 * (§4.2). Amounts in pesos; debits must equal credits.
 */
class JournalEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('entry_date')->default(now())->required(),
            Textarea::make('memo')->columnSpanFull(),

            Repeater::make('lines')
                ->label('Lines')
                ->columnSpanFull()
                ->minItems(2)
                ->defaultItems(2)
                ->columns(12)
                ->schema([
                    Select::make('account_id')
                        ->label('Account')
                        ->options(fn () => Account::query()->orderBy('code')->get()
                            ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"]))
                        ->searchable()->required()->columnSpan(4),
                    TextInput::make('debit')->label('Debit (P)')->numeric()->default(0)->columnSpan(2),
                    TextInput::make('credit')->label('Credit (P)')->numeric()->default(0)->columnSpan(2),
                    TextInput::make('memo')->columnSpan(4),
                    Select::make('department_id')->label('Dept')
                        ->options(fn () => Department::query()->pluck('name', 'id'))->columnSpan(3),
                    Select::make('project_id')->label('Project')
                        ->options(fn () => Project::query()->pluck('name', 'id'))->columnSpan(3),
                    Select::make('fund_id')->label('Fund')
                        ->options(fn () => Fund::query()->pluck('name', 'id'))->columnSpan(3),
                    Select::make('branch_id')->label('Branch')
                        ->options(fn () => Branch::query()->pluck('name', 'id'))->columnSpan(3),
                ]),
        ]);
    }
}
