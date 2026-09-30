<?php

declare(strict_types=1);

namespace App\Actions\Payables;

use App\Data\Payables\BillData;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Bills\BillResource;
use App\Models\Bill;
use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Payables\BillLineCalculator;
use App\Services\Workflow\ApprovalNotifier;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Saves a bill as a draft awaiting approval (maker-checker, §4.2): the same
 * figures posting would produce, but no number, no entry and no stock
 * received until a poster approves it. Approvers are told.
 */
final class SaveDraftBill
{
    public function __construct(
        private readonly BillLineCalculator $calculator,
        private readonly ApprovalNotifier $notifier,
    ) {}

    public function handle(BillData $data, ?User $actor = null): Bill
    {
        return DB::transaction(function () use ($data, $actor): Bill {
            /** @var Company $company */
            $company = Company::query()->withoutGlobalScopes()->findOrFail($data->company_id);
            /** @var Vendor $vendor */
            $vendor = Vendor::query()->withoutGlobalScopes()->where('company_id', $company->id)->findOrFail($data->vendor_id);
            if ($data->lines->count() === 0) {
                throw new RuntimeException('A bill needs at least one line.');
            }
            $computed = $this->calculator->compute($company, $data);

            $bill = new Bill;
            $bill->forceFill([
                'company_id' => $company->id,
                'vendor_id' => $vendor->id,
                'bill_date' => $data->bill_date,
                'due_date' => $data->due_date ?? Carbon::parse($data->bill_date)->addDays($vendor->terms_days)->toDateString(),
                'status' => InvoiceStatus::Draft,
                'pricing_mode' => $data->pricing_mode,
                'is_opening' => $data->is_opening,
                'vatable_purchases' => $computed['totals']['vatable'],
                'input_vat' => $computed['totals']['input_vat'],
                'exempt_purchases' => $computed['totals']['exempt'],
                'total' => $computed['totals']['total'],
                'memo' => $data->memo,
                'reference_no' => $data->reference_no,
                'external_reference_no' => $data->external_reference_no,
                'remarks' => $data->remarks,
                'purchase_order_id' => $data->purchase_order_id,
                'created_by' => $data->created_by ?? $actor?->id,
                'department_id' => $data->department_id,
                'project_id' => $data->project_id,
                'fund_id' => $data->fund_id,
                'branch_id' => $data->branch_id,
            ])->save();
            foreach ($computed['lines'] as $line) {
                $bill->lines()->create($line['model']);
            }

            $this->notifier->awaitingApproval($company, RbacRegistry::BILL_POST, $bill->load('vendor'), 'bill',
                BillResource::getUrl('view', ['record' => $bill, 'tenant' => $company]), $bill->created_by);

            return $bill->load('lines');
        });
    }
}
