<?php

declare(strict_types=1);

namespace App\Actions\Receivables;

use App\Data\Receivables\InvoiceData;
use App\Enums\InvoiceStatus;
use App\Exceptions\Ledger\UnapprovedDocumentException;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use App\Services\Workflow\ApprovalNotifier;
use Illuminate\Support\Facades\DB;

/**
 * Approves and posts a draft invoice (§4.2): a poster other than the maker
 * (while the company requires approval) rebuilds the draft's data and
 * sends it through PostInvoice, which posts it in place. The maker hears.
 */
final class PostDraftInvoice
{
    public function __construct(
        private readonly PostInvoice $post,
        private readonly ApprovalNotifier $notifier,
    ) {}

    public function handle(Invoice $draft, User $approver): Invoice
    {
        if ($draft->status !== InvoiceStatus::Draft) {
            throw UnapprovedDocumentException::make('only a draft invoice can be approved and posted');
        }
        /** @var Company $company */
        $company = Company::query()->withoutGlobalScopes()->findOrFail($draft->company_id);
        if ($company->require_approval && $draft->created_by === $approver->id) {
            throw UnapprovedDocumentException::make('you cannot approve your own invoice; another poster must');
        }

        return DB::transaction(function () use ($draft, $approver, $company): Invoice {
            $draft->loadMissing('lines');
            $data = InvoiceData::from([
                'company_id' => $draft->company_id,
                'customer_id' => $draft->customer_id,
                'invoice_date' => $draft->invoice_date->toDateString(),
                'due_date' => $draft->due_date?->toDateString(),
                'pricing_mode' => $draft->pricing_mode,
                'is_opening' => $draft->is_opening,
                'memo' => $draft->memo,
                'reference_no' => $draft->reference_no,
                'external_reference_no' => $draft->external_reference_no,
                'remarks' => $draft->remarks,
                'sales_order_id' => $draft->sales_order_id,
                'department_id' => $draft->department_id,
                'project_id' => $draft->project_id,
                'fund_id' => $draft->fund_id,
                'branch_id' => $draft->branch_id,
                'created_by' => $draft->created_by,
                'approved_by' => $approver->id,
                'lines' => $draft->lines->sortBy('line_no')->values()->map(fn (InvoiceLine $line): array => [
                    'item_id' => $line->item_id,
                    'description' => $line->description,
                    'qty' => (string) $line->qty,
                    'unit_price' => $line->unit_price->minor,
                    'tax_code_id' => (int) $line->tax_code_id,
                    'income_account_id' => $line->income_account_id,
                    'sales_order_line_id' => $line->sales_order_line_id,
                    'department_id' => $line->department_id,
                    'project_id' => $line->project_id,
                    'fund_id' => $line->fund_id,
                    'branch_id' => $line->branch_id,
                ])->all(),
            ]);

            $posted = $this->post->handle($data, $approver, $draft);
            $this->notifier->posted($posted->load('customer'), 'invoice',
                InvoiceResource::getUrl('view', ['record' => $posted, 'tenant' => $company]), $posted->created_by, $approver);

            return $posted;
        });
    }
}
