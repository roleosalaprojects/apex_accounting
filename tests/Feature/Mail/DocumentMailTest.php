<?php

declare(strict_types=1);

use App\Actions\Receivables\PostInvoice;
use App\Data\Receivables\InvoiceData;
use App\Enums\CompanyRole;
use App\Filament\Pages\Reports\Dunning;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Mail\CustomerStatementMail;
use App\Mail\InvoiceMail;
use App\Mail\OverdueReminderMail;
use App\Mail\PurchaseOrderMail;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\SentEmail;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Services\Mail\DocumentMailer;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
 * Documents leave the system by email with their PDF attached, from the
 * company's own address, and every send is on record.
 */
beforeEach(function () {
    Mail::fake();
    $this->company = makeCompany();
    $this->company->update(['email' => 'billing@dari.test']);
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id, 'name' => 'Golden Harvest', 'email' => 'ap@goldenharvest.test', 'terms_days' => 15]);
    $this->exempt = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->value('id');
    $this->travelTo('2026-06-30');
});

function invoiceFor($test, string $date, int $pesos)
{
    return app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $test->company->id, 'customer_id' => $test->customer->id, 'invoice_date' => $date,
        'lines' => [['description' => 'Rice', 'qty' => '1', 'unit_price' => $pesos * 100, 'tax_code_id' => $test->exempt,
            'income_account_id' => account($test->company, '4100')->id]],
    ]));
}

it('emails an invoice with its PDF from the company address and keeps a record', function () {
    $invoice = invoiceFor($this, '2026-06-10', 56_000);

    $sent = app(DocumentMailer::class)->invoice($invoice, ['ap@goldenharvest.test', 'owner@goldenharvest.test'], 'Please find attached.', $this->owner);

    Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) use ($invoice): bool {
        $mail->render();   // hydrates the envelope, content and attachments as sending would

        return $mail->hasTo('ap@goldenharvest.test') && $mail->hasTo('owner@goldenharvest.test')
            && $mail->hasFrom('billing@dari.test') && $mail->hasReplyTo('billing@dari.test')
            && $mail->hasSubject("Invoice {$invoice->number} from {$this->company->name}")
            && count($mail->rawAttachments) === 1 && $mail->rawAttachments[0]['name'] === "{$invoice->number}.pdf"
            && str_starts_with($mail->rawAttachments[0]['data'], '%PDF');
    });
    expect($sent)->toBeInstanceOf(SentEmail::class)
        ->and($sent->recipients)->toBe(['ap@goldenharvest.test', 'owner@goldenharvest.test'])
        ->and($sent->about_id)->toBe($invoice->id)
        ->and(AuditLog::query()->where('action', 'email.sent')->where('auditable_id', $invoice->id)->exists())->toBeTrue()
        ->and($invoice->sentEmails()->count())->toBe(1);

    $html = (new InvoiceMail($invoice, 'Please find attached.'))->render();
    expect($html)->toContain($invoice->number)->toContain('56,000.00')->toContain('Please find attached.')->toContain('Golden Harvest');
});

it('sends a statement and, when something is overdue, a reminder listing the overdue invoices', function () {
    invoiceFor($this, '2026-05-01', 10_000);   // due May 16: overdue
    invoiceFor($this, '2026-06-25', 20_000);   // due Jul 10: not yet

    app(DocumentMailer::class)->statement($this->customer, '2026-01-01', '2026-06-30', ['ap@goldenharvest.test'], 'Statement attached.', $this->owner);
    Mail::assertSent(CustomerStatementMail::class, fn (CustomerStatementMail $mail): bool => $mail->hasTo('ap@goldenharvest.test') && str_contains($mail->render(), '30,000.00'));

    $reminder = app(DocumentMailer::class)->reminder($this->customer, ['ap@goldenharvest.test'], 'Kindly settle the overdue amount.', $this->owner);
    Mail::assertSent(OverdueReminderMail::class, function (OverdueReminderMail $mail): bool {
        $html = $mail->render();

        return $mail->hasTo('ap@goldenharvest.test') && str_contains($html, 'INV-2026-000001') && ! str_contains($html, 'INV-2026-000002')
            && str_contains($html, '10,000.00') && str_contains($html, '45 days');
    });
    expect($reminder->subject)->toContain('overdue');

    $paidUp = Customer::factory()->create(['company_id' => $this->company->id, 'email' => 'x@y.test']);
    expect(fn () => app(DocumentMailer::class)->reminder($paidUp, ['x@y.test'], 'Hi', $this->owner))->toThrow(RuntimeException::class, 'nothing overdue');
});

it('emails every overdue customer with an address from the dunning page, and says who was skipped', function () {
    invoiceFor($this, '2026-05-01', 10_000);
    $noEmail = Customer::factory()->create(['company_id' => $this->company->id, 'name' => 'Walk-in', 'email' => null]);
    app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id, 'customer_id' => $noEmail->id, 'invoice_date' => '2026-04-01',
        'lines' => [['description' => 'Rice', 'qty' => '1', 'unit_price' => 5_000_00, 'tax_code_id' => $this->exempt,
            'income_account_id' => account($this->company, '4100')->id]],
    ]));
    $this->actingAs($this->owner);
    Filament::setTenant($this->company);

    Livewire::test(Dunning::class)
        ->callAction('email_reminders', ['message' => 'Kindly settle.'])
        ->assertNotified('1 reminder sent · 1 customer without an email address (Walk-in)');

    Mail::assertSent(OverdueReminderMail::class, 1);
    expect(SentEmail::query()->count())->toBe(1);
});

it('offers the email actions on the invoice, customer and purchase order screens', function () {
    $invoice = invoiceFor($this, '2026-05-01', 10_000);
    $this->actingAs($this->owner);
    Filament::setTenant($this->company);

    Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
        ->mountAction('email')
        ->assertActionDataSet(['to' => ['ap@goldenharvest.test'], 'subject' => "Invoice {$invoice->number} from {$this->company->name}"])
        ->callMountedAction()
        ->assertNotified('Emailed to ap@goldenharvest.test');
    Mail::assertSent(InvoiceMail::class, 1);

    Livewire::test(ViewCustomer::class, ['record' => $this->customer->getRouteKey()])
        ->assertActionVisible('email_statement')
        ->assertActionVisible('send_reminder')
        ->callAction('send_reminder', ['to' => ['ap@goldenharvest.test'], 'subject' => 'Overdue', 'message' => 'Please pay.'])
        ->assertNotified('Emailed to ap@goldenharvest.test');
    Mail::assertSent(OverdueReminderMail::class, 1);

    $vendor = Vendor::factory()->create(['company_id' => $this->company->id, 'email' => 'sales@ricetrader.test']);
    $po = PurchaseOrder::factory()->create(['company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'number' => 'PO-2026-000001']);
    $po->lines()->create(['description' => 'Rice', 'qty' => '10', 'unit_price' => 1_000_00, 'tax_code_id' => $this->exempt,
        'expense_account_id' => account($this->company, '6300')->id]);
    Livewire::test(ListPurchaseOrders::class)
        ->callTableAction('email', $po, ['to' => ['sales@ricetrader.test'], 'subject' => 'PO', 'message' => 'Please supply.'])
        ->assertNotified('Emailed to sales@ricetrader.test');
    Mail::assertSent(PurchaseOrderMail::class, fn (PurchaseOrderMail $mail): bool => $mail->hasTo('sales@ricetrader.test') && str_contains($mail->render(), 'PO-2026-000001'));
});

it('refuses to email a customer document with no address at all', function () {
    $invoice = invoiceFor($this, '2026-06-10', 1_000);

    expect(fn () => app(DocumentMailer::class)->invoice($invoice, [], 'Hi', $this->owner))->toThrow(RuntimeException::class, 'recipient');
    Mail::assertNothingSent();
});
