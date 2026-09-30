<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Enums\InvoiceStatus;
use App\Mail\CustomerStatementMail;
use App\Mail\DocumentMail;
use App\Mail\InvoiceMail;
use App\Mail\OverdueReminderMail;
use App\Mail\PurchaseOrderMail;
use App\Mail\SalesOrderMail;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\SentEmail;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Sends documents out by email — from the company's own address when it has
 * one — and keeps a record of every send, on the document and in the audit
 * log. The one place mail leaves the system from.
 */
final class DocumentMailer
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<string>  $to
     */
    public function invoice(Invoice $invoice, array $to, string $message, ?User $actor = null): SentEmail
    {
        $invoice->loadMissing('company');

        return $this->deliver($invoice->company, $invoice, new InvoiceMail($invoice, $message), $to, $actor);
    }

    /**
     * @param  list<string>  $to
     */
    public function statement(Customer $customer, string $from, string $asOf, array $to, string $message, ?User $actor = null): SentEmail
    {
        $customer->loadMissing('company');

        return $this->deliver($customer->company, $customer, new CustomerStatementMail($customer, $from, $asOf, $message), $to, $actor);
    }

    /**
     * @param  list<string>  $to
     */
    public function reminder(Customer $customer, array $to, string $message, ?User $actor = null): SentEmail
    {
        $overdue = $this->overdueInvoices($customer);
        if ($overdue->isEmpty()) {
            throw new RuntimeException("{$customer->name} has nothing overdue.");
        }
        $customer->loadMissing('company');

        return $this->deliver($customer->company, $customer, new OverdueReminderMail($customer, $overdue, $message), $to, $actor);
    }

    /**
     * @param  list<string>  $to
     */
    public function purchaseOrder(PurchaseOrder $order, array $to, string $message, ?User $actor = null): SentEmail
    {
        $order->loadMissing('company');

        return $this->deliver($order->company, $order, new PurchaseOrderMail($order, $message), $to, $actor);
    }

    /**
     * @param  list<string>  $to
     */
    public function salesOrder(SalesOrder $order, array $to, string $message, ?User $actor = null): SentEmail
    {
        $order->loadMissing('company');

        return $this->deliver($order->company, $order, new SalesOrderMail($order, $message), $to, $actor);
    }

    /**
     * A reminder to every customer with something overdue and an address to
     * send it to; the rest are named so someone can chase them another way.
     *
     * @return array{sent: list<string>, skipped: list<string>}
     */
    public function remindAllOverdue(Company $company, string $message, ?User $actor = null): array
    {
        $customerIds = Invoice::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('status', [InvoiceStatus::Posted, InvoiceStatus::PartiallyPaid])
            ->whereDate('due_date', '<', Carbon::today())
            ->distinct()->pluck('customer_id');

        $sent = [];
        $skipped = [];
        foreach (Customer::query()->withoutGlobalScopes()->whereIn('id', $customerIds)->orderBy('name')->get() as $customer) {
            if ($this->overdueInvoices($customer)->isEmpty()) {
                continue;
            }
            if (blank($customer->email)) {
                $skipped[] = $customer->name;

                continue;
            }
            $this->reminder($customer, [$customer->email], $message, $actor);
            $sent[] = $customer->name;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * The customer's posted invoices past their due date with a balance open.
     *
     * @return Collection<int, Invoice>
     */
    public function overdueInvoices(Customer $customer): Collection
    {
        return Invoice::query()->withoutGlobalScopes()
            ->where('company_id', $customer->company_id)->where('customer_id', $customer->id)
            ->whereIn('status', [InvoiceStatus::Posted, InvoiceStatus::PartiallyPaid])
            ->whereDate('due_date', '<', Carbon::today())
            ->orderBy('due_date')
            ->get()
            ->filter(fn (Invoice $invoice): bool => $invoice->outstanding() > 0)
            ->values();
    }

    /** Total still open on the customer's overdue invoices. */
    public function overdueTotal(Customer $customer): int
    {
        return (int) $this->overdueInvoices($customer)->sum(fn (Invoice $invoice): int => $invoice->outstanding());
    }

    /**
     * @param  list<string>  $to
     */
    private function deliver(Company $company, Model $about, DocumentMail $mail, array $to, ?User $actor): SentEmail
    {
        $to = array_values(array_unique(array_filter(array_map(fn (mixed $address): string => trim((string) $address), $to))));
        if ($to === []) {
            throw new RuntimeException('Add at least one recipient.');
        }
        foreach ($to as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException("{$address} is not a valid email address.");
            }
        }

        Mail::to($to)->send($mail);

        $subject = $mail->envelope()->subject ?? '';
        $sent = SentEmail::query()->create([
            'company_id' => $company->id,
            'user_id' => $actor?->id,
            'about_type' => $about->getMorphClass(),
            'about_id' => $about->getKey(),
            'mailable' => $mail::class,
            'subject' => $subject,
            'recipients' => $to,
            'sent_at' => now(),
        ]);
        $this->audit->record($company->id, 'email.sent', $about, null, ['to' => $to, 'subject' => $subject]);

        return $sent;
    }
}
