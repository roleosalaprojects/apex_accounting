<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\Invoice;
use App\Services\Printing\PrintInvoice;

final class InvoiceMail extends DocumentMail
{
    public function __construct(public readonly Invoice $invoice, string $message)
    {
        parent::__construct($message);
        $this->invoice->loadMissing(['company', 'customer']);
    }

    public static function defaultSubject(Invoice $invoice): string
    {
        $invoice->loadMissing('company');

        return "Invoice {$invoice->number} from {$invoice->company->name}";
    }

    public static function defaultMessage(Invoice $invoice): string
    {
        $invoice->loadMissing(['company', 'customer']);
        $due = $invoice->due_date?->format('M j, Y');

        return 'Dear '.($invoice->customer->contact_person ?: $invoice->customer->name).",\n\n"
            ."Please find attached Invoice {$invoice->number} dated {$invoice->invoice_date->format('M j, Y')} for {$invoice->total->format()}"
            .($due ? ", due on {$due}" : '').".\n\n"
            ."Thank you for your business.\n\n{$invoice->company->name}";
    }

    public function company(): Company
    {
        return $this->invoice->company;
    }

    public function partyName(): string
    {
        return $this->invoice->customer->name;
    }

    protected function subjectLine(): string
    {
        return self::defaultSubject($this->invoice);
    }

    protected function facts(): array
    {
        return array_filter([
            'Invoice' => (string) $this->invoice->number,
            'Date' => $this->invoice->invoice_date->format('M j, Y'),
            'Due' => $this->invoice->due_date?->format('M j, Y'),
            'Amount' => $this->peso($this->invoice->total->minor),
            'Balance' => $this->peso($this->invoice->outstanding()),
        ]);
    }

    /**
     * @return array{name: string, pdf: string}
     */
    protected function pdf(): array
    {
        return ['name' => "{$this->invoice->number}.pdf", 'pdf' => app(PrintInvoice::class)->render($this->invoice)];
    }
}
