<?php

declare(strict_types=1);

namespace App\Actions\Recurring;

use App\Actions\Assets\RunMonthlyDepreciation;
use App\Actions\Ledger\CreateDraftJournalEntry;
use App\Actions\Ledger\PostJournalEntry;
use App\Actions\Payables\PostBill;
use App\Actions\Receivables\PostInvoice;
use App\Data\Ledger\JournalEntryData;
use App\Data\Payables\BillData;
use App\Data\Receivables\InvoiceData;
use App\Enums\RecurringKind;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\RecurringRun;
use App\Models\RecurringTemplate;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Instantiates all recurring templates due on/before a date (§11). Each
 * template runs in isolation — one failure is logged and does not halt the
 * batch. next_run_on advances only on success.
 *
 * Whoever triggers the run (a user, or the scheduler) only needs
 * recurring.run. Each template posts under the authority of the user who last
 * saved it, so the posting engine checks *that* user's journal.post: editing a
 * template never lets someone post what they could not post by hand. A
 * template with no editor on record can still create drafts but never posts.
 */
final class RunDueTemplates
{
    /** Payload keys a template may not set: who prepared/approved it, and ledger linkage. */
    private const RESERVED_KEYS = [
        'company_id', 'created_by', 'approved_by',
        'source_type', 'source_id', 'reversal_of_id', 'reversal_reason',
    ];

    public function __construct(
        private readonly PostJournalEntry $post,
        private readonly CreateDraftJournalEntry $draft,
        private readonly PostInvoice $postInvoice,
        private readonly PostBill $postBill,
        private readonly RunMonthlyDepreciation $depreciation,
    ) {}

    /**
     * @return array<int, RecurringRun>
     */
    public function handle(Company $company, string $asOf, ?User $triggeredBy = null): array
    {
        if ($triggeredBy !== null && ! $triggeredBy->hasCompanyPermission($company->id, RbacRegistry::RECURRING_RUN)) {
            throw new RuntimeException('You do not have permission to run recurring templates.');
        }

        $templates = RecurringTemplate::query()
            ->withoutGlobalScopes()
            ->with('updatedBy')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereDate('next_run_on', '<=', $asOf)
            ->where(function ($q) use ($asOf): void {
                $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $asOf);
            })
            ->orderBy('next_run_on')
            ->get();

        $runs = [];

        foreach ($templates as $template) {
            $runDate = $template->next_run_on->toDateString();

            try {
                $runs[] = DB::transaction(function () use ($company, $template, $runDate): RecurringRun {
                    [$document, $status] = $this->instantiate($company, $template, $runDate);

                    $run = RecurringRun::query()->create([
                        'company_id' => $company->id,
                        'recurring_template_id' => $template->id,
                        'ran_on' => $runDate,
                        'created_document_type' => $document?->getMorphClass(),
                        'created_document_id' => $document?->getKey(),
                        'status' => $status,
                    ]);

                    $template->forceFill(['next_run_on' => $template->schedule->advance($template->next_run_on)->toDateString()])->save();

                    return $run;
                });
            } catch (Throwable $e) {
                $runs[] = RecurringRun::query()->create([
                    'company_id' => $company->id,
                    'recurring_template_id' => $template->id,
                    'ran_on' => $runDate,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $runs;
    }

    /**
     * @return array{0: Model|null, 1: string}
     */
    private function instantiate(Company $company, RecurringTemplate $template, string $runDate): array
    {
        $payload = array_diff_key($template->payload ?? [], array_flip(self::RESERVED_KEYS));
        $payload['company_id'] = $company->id;

        return match ($template->kind) {
            RecurringKind::JournalEntry => $this->runJournalEntry($template, $payload, $runDate),
            RecurringKind::Invoice => [
                $this->postInvoice->handle(
                    InvoiceData::from(array_merge($payload, ['invoice_date' => $runDate], $this->signatories($template))),
                    $this->poster($template),
                ),
                'posted',
            ],
            RecurringKind::Bill => [
                $this->postBill->handle(
                    BillData::from(array_merge($payload, ['bill_date' => $runDate], $this->signatories($template))),
                    $this->poster($template),
                ),
                'posted',
            ],
            RecurringKind::DepreciationRun => $this->runDepreciation($company, $template, $runDate),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: Model, 1: string}
     */
    private function runJournalEntry(RecurringTemplate $template, array $payload, string $runDate): array
    {
        $payload['entry_date'] = $runDate;

        if (! $template->auto_post) {
            return [$this->draft->handle(JournalEntryData::from([...$payload, 'created_by' => $template->updated_by])), 'created'];
        }

        $data = JournalEntryData::from(array_merge($payload, $this->signatories($template)));

        return [$this->post->handle($data, $this->poster($template)), 'posted'];
    }

    /**
     * @return array{0: Model|null, 1: string}
     */
    private function runDepreciation(Company $company, RecurringTemplate $template, string $runDate): array
    {
        $period = AccountingPeriod::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->containing($runDate)
            ->first();

        if ($period === null) {
            throw new RuntimeException("No period for depreciation run on {$runDate}.");
        }

        $entries = $this->depreciation->handle($company, $period, $this->poster($template));

        return [$entries[0] ?? null, 'posted'];
    }

    /**
     * The user a posting run acts as. PostJournalEntry then enforces their
     * journal.post permission in this company.
     */
    private function poster(RecurringTemplate $template): User
    {
        return $template->updatedBy
            ?? throw new RuntimeException('Template has no editor on record; re-save it (as a user who can post) before it can post.');
    }

    /**
     * @return array{created_by: int, approved_by: int}
     */
    private function signatories(RecurringTemplate $template): array
    {
        $poster = $this->poster($template);

        return ['created_by' => $poster->id, 'approved_by' => $poster->id];
    }
}
