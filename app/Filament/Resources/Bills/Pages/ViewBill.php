<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bills\Pages;

use App\Actions\Payables\PostDraftBill;
use App\Actions\Payables\RejectDraftBill;
use App\Actions\Payables\VoidBill;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Bills\Actions\PayBillAction;
use App\Filament\Resources\Bills\BillResource;
use App\Filament\Resources\DebitMemos\DebitMemoResource;
use App\Filament\Support\ApprovalActions;
use App\Filament\Support\AttachFilesAction;
use App\Filament\Support\LoadsRecordRelations;
use App\Filament\Support\VoidAction;
use App\Models\Bill;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewBill extends ViewRecord
{
    use LoadsRecordRelations;

    protected static string $resource = BillResource::class;

    protected function recordRelations(): array
    {
        return ['vendor', 'lines.department', 'lines.project', 'lines.fund', 'lines.branch', 'attachments.uploader', 'department', 'project', 'fund', 'branch'];
    }

    protected function getHeaderActions(): array
    {
        return [
            ApprovalActions::post('bill', fn (Bill $draft, User $user): Bill => app(PostDraftBill::class)->handle($draft, $user)),
            ApprovalActions::reject('bill', fn (Bill $draft, string $reason, User $user) => app(RejectDraftBill::class)->handle($draft, $reason, $user), BillResource::getUrl('index')),
            PayBillAction::make(),
            VoidAction::make(
                'bill',
                fn (Bill $bill, string $reason, User $user) => app(VoidBill::class)->handle($bill, $reason, $user),
                fn (Bill $bill): bool => in_array($bill->status, [InvoiceStatus::Posted, InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid], true),
            ),
            Action::make('debit_memo')->label('Debit memo')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                ->visible(fn (Bill $bill): bool => DebitMemoResource::canCreate()
                    && in_array($bill->status, [InvoiceStatus::Posted, InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid], true))
                ->url(fn (Bill $bill): string => DebitMemoResource::getUrl('create', ['bill' => $bill->id])),
            AttachFilesAction::make(),
        ];
    }
}
