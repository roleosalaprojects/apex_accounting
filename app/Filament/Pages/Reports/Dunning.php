<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Models\User;
use App\Services\Mail\DocumentMailer;
use App\Services\Reports\DunningReport;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class Dunning extends ReportPage
{
    protected static ?string $navigationLabel = 'Dunning / Overdue';

    protected static ?int $navigationSort = 17;

    public function getTitle(): string
    {
        return 'Dunning / Overdue Receivables';
    }

    protected function usesRange(): bool
    {
        return false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('email_reminders')->label('Email reminders')->icon('heroicon-o-bell-alert')->color('warning')
                ->modalHeading('Email a reminder to every overdue customer')
                ->modalDescription('One email per customer with an address on file, listing their overdue invoices. Customers without an email are named afterwards.')
                ->modalSubmitActionLabel('Send reminders')
                ->schema([
                    Textarea::make('message')->rows(8)->required()
                        ->default("Dear Customer,\n\nOur records show the invoices below are past due. Kindly settle them at your earliest convenience, or let us know if you have any question about them.\n\nIf payment has already been made, please disregard this reminder.\n\n".$this->company()->name),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = Auth::user();
                    $result = app(DocumentMailer::class)->remindAllOverdue($this->company(), $data['message'], $user);
                    $sent = count($result['sent']);
                    $skipped = count($result['skipped']);
                    $title = sprintf('%d reminder%s sent', $sent, $sent === 1 ? '' : 's')
                        .($skipped > 0 ? sprintf(' · %d customer%s without an email address (%s)', $skipped, $skipped === 1 ? '' : 's', implode(', ', $result['skipped'])) : '');
                    Notification::make()->{$sent > 0 ? 'success' : 'warning'}()->title($title)->send();
                }),
            ...parent::getHeaderActions(),
        ];
    }

    protected function payload(): array
    {
        $r = app(DunningReport::class)->build($this->company()->id, (string) $this->asOf);

        $rows = [];
        foreach ($r['rows'] as $x) {
            $limit = (int) $x['credit_limit'];
            $status = $x['over_limit'] === true ? 'OVER LIMIT' : ((int) $x['overdue'] > 0 ? 'Overdue' : 'Within terms');

            $rows[] = [
                (string) $x['customer'],
                $this->peso((int) $x['outstanding']),
                $this->peso((int) $x['overdue']),
                $x['oldest_due'] !== null ? $this->date($x['oldest_due']) : '—',
                $limit > 0 ? $this->peso($limit) : '—',
                $status,
            ];
        }

        return [
            'columns' => ['Customer', 'Outstanding', 'Overdue', 'Oldest due', 'Credit limit', 'Status'],
            'rows' => $rows,
            'totals' => ['TOTAL', $this->peso($r['total_outstanding']), $this->peso($r['total_overdue']), '', '', ''],
        ];
    }
}
