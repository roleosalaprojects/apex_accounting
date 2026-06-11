<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Recurring\RunDueTemplates;
use App\Models\Company;
use Illuminate\Console\Command;

/**
 * Daily scheduler entry point for recurring templates (§11).
 */
final class RunRecurring extends Command
{
    protected $signature = 'recurring:run {company? : Company id (defaults to all active)} {--date= : Run as of date (defaults to today)}';

    protected $description = 'Instantiate all due recurring templates.';

    public function handle(RunDueTemplates $action): int
    {
        $asOf = (string) ($this->option('date') ?? now()->toDateString());

        $companies = $this->argument('company') !== null
            ? Company::query()->withoutGlobalScopes()->whereKey($this->argument('company'))->get()
            : Company::query()->withoutGlobalScopes()->where('is_active', true)->get();

        $total = 0;
        foreach ($companies as $company) {
            $runs = $action->handle($company, $asOf);
            $total += count($runs);
            foreach ($runs as $run) {
                $this->line("[{$company->name}] template #{$run->recurring_template_id}: {$run->status}");
            }
        }

        $this->info("Recurring run complete: {$total} template(s) processed as of {$asOf}.");

        return self::SUCCESS;
    }
}
