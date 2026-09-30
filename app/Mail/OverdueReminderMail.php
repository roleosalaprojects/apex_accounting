<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** A polite nudge listing the customer's overdue invoices; no attachment, the numbers are in the message. */
final class OverdueReminderMail extends DocumentMail
{
    /**
     * @param  Collection<int, Invoice>  $overdue
     */
    public function __construct(public readonly Customer $customer, public readonly Collection $overdue, string $message)
    {
        parent::__construct($message);
        $this->customer->loadMissing('company');
    }

    public static function defaultSubject(Customer $customer, int $overdueTotal): string
    {
        $customer->loadMissing('company');

        return 'Reminder: '.(new self($customer, collect(), ''))->peso($overdueTotal)." overdue on your account with {$customer->company->name}";
    }

    public static function defaultMessage(Customer $customer): string
    {
        $customer->loadMissing('company');

        return 'Dear '.($customer->contact_person ?: $customer->name).",\n\n"
            ."Our records show the invoices below are past due. Kindly settle them at your earliest convenience, or let us know if you have any question about them.\n\n"
            ."If payment has already been made, please disregard this reminder.\n\n{$customer->company->name}";
    }

    public function company(): Company
    {
        return $this->customer->company;
    }

    public function partyName(): string
    {
        return $this->customer->name;
    }

    protected function subjectLine(): string
    {
        return self::defaultSubject($this->customer, $this->total());
    }

    protected function facts(): array
    {
        return ['Overdue invoices' => (string) $this->overdue->count(), 'Total overdue' => $this->peso($this->total())];
    }

    /**
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    protected function table(): array
    {
        $today = Carbon::today();

        return [
            'columns' => ['Invoice', 'Date', 'Due', 'Overdue by', 'Balance'],
            'rows' => $this->overdue->map(fn (Invoice $invoice): array => [
                (string) $invoice->number,
                $invoice->invoice_date->format('M j, Y'),
                $invoice->due_date?->format('M j, Y') ?? '',
                $invoice->due_date === null ? '' : (int) $invoice->due_date->diffInDays($today).' days',
                $this->peso($invoice->outstanding()),
            ])->values()->all(),
        ];
    }

    protected function pdf(): ?array
    {
        return null;
    }

    private function total(): int
    {
        return (int) $this->overdue->sum(fn (Invoice $invoice): int => $invoice->outstanding());
    }
}
