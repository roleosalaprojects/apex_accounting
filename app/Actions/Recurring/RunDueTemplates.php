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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Instantiates all recurring templates due on/before a date (§11). Each
 * template runs in isolation — one failure is logged and does not halt the
 * batch. next_run_on advances only on success.
 */
final class RunDueTemplates
{
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
    public function handle(Company $company, string $asOf): array
    {
        $templates = RecurringTemplate::query()
            ->withoutGlobalScopes()
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
        $payload = $template->payload ?? [];
        $payload['company_id'] = $company->id;

        return match ($template->kind) {
            RecurringKind::JournalEntry => $this->runJournalEntry($template, $payload, $runDate),
            RecurringKind::Invoice => [
                $this->postInvoice->handle(InvoiceData::from(array_merge($payload, ['invoice_date' => $runDate]))),
                'posted',
            ],
            RecurringKind::Bill => [
                $this->postBill->handle(BillData::from(array_merge($payload, ['bill_date' => $runDate]))),
                'posted',
            ],
            RecurringKind::DepreciationRun => $this->runDepreciation($company, $runDate),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: Model, 1: string}
     */
    private function runJournalEntry(RecurringTemplate $template, array $payload, string $runDate): array
    {
        $data = JournalEntryData::from(array_merge($payload, ['entry_date' => $runDate]));

        return $template->auto_post
            ? [$this->post->handle($data), 'posted']
            : [$this->draft->handle($data), 'created'];
    }

    /**
     * @return array{0: Model|null, 1: string}
     */
    private function runDepreciation(Company $company, string $runDate): array
    {
        $period = AccountingPeriod::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->containing($runDate)
            ->first();

        if ($period === null) {
            throw new RuntimeException("No period for depreciation run on {$runDate}.");
        }

        $entries = $this->depreciation->handle($company, $period);

        return [$entries[0] ?? null, 'posted'];
    }
}
