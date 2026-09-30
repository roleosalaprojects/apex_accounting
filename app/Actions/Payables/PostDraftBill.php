<?php

declare(strict_types=1);

namespace App\Actions\Payables;

use App\Data\Payables\BillData;
use App\Enums\InvoiceStatus;
use App\Exceptions\Ledger\UnapprovedDocumentException;
use App\Filament\Resources\Bills\BillResource;
use App\Models\Bill;
use App\Models\BillLine;
use App\Models\Company;
use App\Models\User;
use App\Services\Workflow\ApprovalNotifier;
use Illuminate\Support\Facades\DB;

/**
 * Approves and posts a draft bill (§4.2): a poster other than the maker
 * (while the company requires approval) rebuilds the draft's data and
 * sends it through PostBill, which posts it in place. The maker hears.
 */
final class PostDraftBill
{
    public function __construct(
        private readonly PostBill $post,
        private readonly ApprovalNotifier $notifier,
    ) {}

    public function handle(Bill $draft, User $approver): Bill
    {
        if ($draft->status !== InvoiceStatus::Draft) {
            throw UnapprovedDocumentException::make('only a draft bill can be approved and posted');
        }
        /** @var Company $company */
        $company = Company::query()->withoutGlobalScopes()->findOrFail($draft->company_id);
        if ($company->require_approval && $draft->created_by === $approver->id) {
            throw UnapprovedDocumentException::make('you cannot approve your own bill; another poster must');
        }

        return DB::transaction(function () use ($draft, $approver, $company): Bill {
            $draft->loadMissing('lines');
            $data = BillData::from([
                'company_id' => $draft->company_id,
                'vendor_id' => $draft->vendor_id,
                'bill_date' => $draft->bill_date->toDateString(),
                'due_date' => $draft->due_date?->toDateString(),
                'pricing_mode' => $draft->pricing_mode,
                'is_opening' => $draft->is_opening,
                'memo' => $draft->memo,
                'reference_no' => $draft->reference_no,
                'external_reference_no' => $draft->external_reference_no,
                'remarks' => $draft->remarks,
                'purchase_order_id' => $draft->purchase_order_id,
                'department_id' => $draft->department_id,
                'project_id' => $draft->project_id,
                'fund_id' => $draft->fund_id,
                'branch_id' => $draft->branch_id,
                'created_by' => $draft->created_by,
                'approved_by' => $approver->id,
                'lines' => $draft->lines->sortBy('line_no')->values()->map(fn (BillLine $line): array => [
                    'item_id' => $line->item_id,
                    'description' => $line->description,
                    'qty' => (string) $line->qty,
                    'unit_price' => $line->unit_price->minor,
                    'tax_code_id' => (int) $line->tax_code_id,
                    'vat_bucket' => $line->vat_bucket?->value,
                    'expense_or_asset_account_id' => $line->expense_or_asset_account_id,
                    'purchase_order_line_id' => $line->purchase_order_line_id,
                    'department_id' => $line->department_id,
                    'project_id' => $line->project_id,
                    'fund_id' => $line->fund_id,
                    'branch_id' => $line->branch_id,
                ])->all(),
            ]);

            $posted = $this->post->handle($data, $approver, $draft);
            $this->notifier->posted($posted->load('vendor'), 'bill',
                BillResource::getUrl('view', ['record' => $posted, 'tenant' => $company]), $posted->created_by, $approver);

            return $posted;
        });
    }
}
