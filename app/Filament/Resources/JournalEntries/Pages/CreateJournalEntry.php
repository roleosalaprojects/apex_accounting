<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalEntries\Pages;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Ledger\JournalEntryData;
use App\Exceptions\Ledger\LedgerException;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateJournalEntry extends CreateRecord
{
    protected static string $resource = JournalEntryResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Company $company */
        $company = Filament::getTenant();
        /** @var User $actor */
        $actor = Auth::user();

        $lines = array_map(fn (array $l): array => [
            'account_id' => (int) $l['account_id'],
            'debit' => (int) round(((float) ($l['debit'] ?? 0)) * 100),
            'credit' => (int) round(((float) ($l['credit'] ?? 0)) * 100),
            'memo' => $l['memo'] ?? null,
            'department_id' => $l['department_id'] ?? null,
            'project_id' => $l['project_id'] ?? null,
            'fund_id' => $l['fund_id'] ?? null,
            'branch_id' => $l['branch_id'] ?? null,
        ], $data['lines']);

        try {
            return app(PostJournalEntry::class)->handle(JournalEntryData::from([
                'company_id' => $company->id,
                'entry_date' => $data['entry_date'],
                'memo' => $data['memo'] ?? null,
                'lines' => $lines,
            ]), $actor);
        } catch (LedgerException $e) {
            Notification::make()->danger()->title('Could not post entry')->body($e->getMessage())->send();

            throw new Halt;
        }
    }
}
