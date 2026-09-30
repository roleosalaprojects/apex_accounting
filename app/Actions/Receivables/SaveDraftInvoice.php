<?php

declare(strict_types=1);

namespace App\Actions\Receivables;

use App\Data\Receivables\InvoiceData;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Receivables\InvoiceLineCalculator;
use App\Services\Workflow\ApprovalNotifier;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Saves an invoice as a draft awaiting approval (maker-checker, §4.2): the
 * same figures posting would produce, but no number, no entry and no
 * stock movement until a poster approves it. Approvers are told.
 */
final class SaveDraftInvoice
{
    public function __construct(
        private readonly InvoiceLineCalculator $calculator,
        private readonly ApprovalNotifier $notifier,
    ) {}

    public function handle(InvoiceData $data, ?User $actor = null): Invoice
    {
        return DB::transaction(function () use ($data, $actor): Invoice {
            /** @var Company $company */
            $company = Company::query()->withoutGlobalScopes()->findOrFail($data->company_id);
            if ($data->lines->count() === 0) {
                throw new RuntimeException('An invoice needs at least one line.');
            }
            $computed = $this->calculator->compute($company, $data);

            $invoice = new Invoice;
            $invoice->forceFill([
                'company_id' => $company->id,
                'customer_id' => $data->customer_id,
                'invoice_date' => $data->invoice_date,
                'due_date' => $data->due_date,
                'status' => InvoiceStatus::Draft,
                'pricing_mode' => $data->pricing_mode,
                'is_opening' => $data->is_opening,
                'vatable_sales' => $computed['totals']['vatable'],
                'vat_amount' => $computed['totals']['vat'],
                'exempt_sales' => $computed['totals']['exempt'],
                'zero_rated_sales' => $computed['totals']['zero'],
                'total' => $computed['totals']['total'],
                'memo' => $data->memo,
                'reference_no' => $data->reference_no,
                'external_reference_no' => $data->external_reference_no,
                'remarks' => $data->remarks,
                'sales_order_id' => $data->sales_order_id,
                'created_by' => $data->created_by ?? $actor?->id,
                'department_id' => $data->department_id,
                'project_id' => $data->project_id,
                'fund_id' => $data->fund_id,
                'branch_id' => $data->branch_id,
            ])->save();
            foreach ($computed['lines'] as $line) {
                $invoice->lines()->create($line['model']);
            }

            $this->notifier->awaitingApproval($company, RbacRegistry::INVOICE_POST, $invoice->load('customer'), 'invoice',
                InvoiceResource::getUrl('view', ['record' => $invoice, 'tenant' => $company]), $invoice->created_by);

            return $invoice->load('lines');
        });
    }
}
