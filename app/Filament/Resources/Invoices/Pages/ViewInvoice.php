<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Pages;

use App\Actions\Receivables\VoidInvoice;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Support\AttachFilesAction;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Printing\PrintInvoice;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AttachFilesAction::make(),
            Action::make('pdf')->label('Download PDF')->icon('heroicon-o-document-arrow-down')
                ->visible(fn (): bool => $this->record->status !== InvoiceStatus::Draft)
                ->action(function (): StreamedResponse {
                    /** @var Invoice $invoice */
                    $invoice = $this->record;

                    return response()->streamDownload(
                        function () use ($invoice): void {
                            echo app(PrintInvoice::class)->render($invoice);
                        },
                        ($invoice->number ?? 'invoice').'.pdf',
                    );
                }),
            Action::make('void')->label('Void')->icon('heroicon-o-no-symbol')->color('danger')
                ->visible(fn (): bool => in_array($this->record->status, [InvoiceStatus::Posted, InvoiceStatus::PartiallyPaid], true))
                ->requiresConfirmation()
                ->schema([Textarea::make('reason')->required()->minLength(5)])
                ->action(function (array $data): void {
                    /** @var Invoice $invoice */
                    $invoice = $this->record;
                    /** @var User $user */
                    $user = Auth::user();

                    try {
                        app(VoidInvoice::class)->handle($invoice, $data['reason'], $user);
                        Notification::make()->success()->title('Invoice voided')->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('Could not void')->body($e->getMessage())->send();
                    }
                }),
        ];
    }
}
