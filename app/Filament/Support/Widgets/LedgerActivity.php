<?php

declare(strict_types=1);

namespace App\Filament\Support\Widgets;

use App\Enums\JournalStatus;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Support\Peso;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Fund;
use App\Models\JournalLine;
use App\Models\Project;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Posted journal lines behind the record a view page shows: the lines tagged
 * with a department, project, fund or branch, or the lines on an account (a
 * bank account's ledger account included). Newest first; rows open the entry.
 */
class LedgerActivity extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $byAccount = $this->record instanceof Account || $this->record instanceof BankAccount;

        return $table
            ->heading($byAccount ? 'Ledger' : 'Tagged transactions')
            ->query(fn (): Builder => $this->lines())
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('journalEntry.entry_date')->label('Date')->date('M j, Y'),
                TextColumn::make('journalEntry.number')->label('Entry'),
                TextColumn::make('account.name')->label('Account')
                    ->formatStateUsing(fn (JournalLine $record): string => $record->account?->code.' · '.$record->account?->name)
                    ->hidden($byAccount),
                TextColumn::make('memo')->label('Memo')->limit(60)->wrap()
                    ->state(fn (JournalLine $record): ?string => $record->memo ?? $record->journalEntry?->memo)
                    ->placeholder('—'),
                TextColumn::make('debit')->alignEnd()
                    ->formatStateUsing(fn (Money $state): string => $state->minor === 0 ? '' : Peso::format($state)),
                TextColumn::make('credit')->alignEnd()
                    ->formatStateUsing(fn (Money $state): string => $state->minor === 0 ? '' : Peso::format($state)),
            ])
            ->recordUrl(fn (JournalLine $record): string => JournalEntryResource::getUrl('view', ['record' => $record->journal_entry_id]));
    }

    /**
     * @return Builder<JournalLine>
     */
    private function lines(): Builder
    {
        $query = JournalLine::query()
            ->with(['journalEntry', 'account'])
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $this->record?->getAttribute('company_id'))
            ->whereIn('journal_entries.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
            ->select('journal_lines.*')
            ->orderByDesc('journal_entries.entry_date')
            ->orderByDesc('journal_lines.id');

        return match (true) {
            $this->record instanceof Department => $query->where('journal_lines.department_id', $this->record->getKey()),
            $this->record instanceof Project => $query->where('journal_lines.project_id', $this->record->getKey()),
            $this->record instanceof Fund => $query->where('journal_lines.fund_id', $this->record->getKey()),
            $this->record instanceof Branch => $query->where('journal_lines.branch_id', $this->record->getKey()),
            $this->record instanceof Account => $query->where('journal_lines.account_id', $this->record->getKey()),
            $this->record instanceof BankAccount => $query->where('journal_lines.account_id', $this->record->account_id),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
