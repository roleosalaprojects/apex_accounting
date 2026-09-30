<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Support\EmailAction;
use App\Filament\Support\FiscalYear;
use App\Filament\Support\PrintAction;
use App\Mail\CustomerStatementMail;
use App\Mail\OverdueReminderMail;
use App\Models\Customer;
use App\Models\SentEmail;
use App\Models\User;
use App\Services\Mail\DocumentMailer;
use App\Services\Printing\PrintCustomerStatement;
use Carbon\CarbonImmutable;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PrintAction::make('statement', 'Statement',
                fn (Customer $customer): string => app(PrintCustomerStatement::class)->render(
                    $customer, FiscalYear::start()->toDateString(), CarbonImmutable::today()->toDateString(),
                ),
                fn (Customer $customer): string => 'SOA-'.$customer->code.'-'.CarbonImmutable::today()->format('Ymd').'.pdf',
            )->icon('heroicon-o-document-text'),
            EmailAction::make('email_statement', 'Email statement',
                fn (Customer $customer): array => [
                    'to' => array_filter([$customer->email]),
                    'subject' => CustomerStatementMail::defaultSubject($customer, CarbonImmutable::today()->toDateString()),
                    'message' => CustomerStatementMail::defaultMessage($customer, FiscalYear::start()->toDateString(), CarbonImmutable::today()->toDateString()),
                ],
                fn (Customer $customer, array $data, User $user): SentEmail => app(DocumentMailer::class)->statement(
                    $customer, FiscalYear::start()->toDateString(), CarbonImmutable::today()->toDateString(), $data['to'], $data['message'], $user,
                ),
            ),
            EmailAction::make('send_reminder', 'Send reminder',
                fn (Customer $customer): array => [
                    'to' => array_filter([$customer->email]),
                    'subject' => OverdueReminderMail::defaultSubject($customer, app(DocumentMailer::class)->overdueTotal($customer)),
                    'message' => OverdueReminderMail::defaultMessage($customer),
                ],
                fn (Customer $customer, array $data, User $user): SentEmail => app(DocumentMailer::class)->reminder($customer, $data['to'], $data['message'], $user),
            )->color('warning')->icon('heroicon-o-bell-alert')
                ->visible(fn (Customer $customer): bool => app(DocumentMailer::class)->overdueInvoices($customer)->isNotEmpty()),
            EditAction::make(),
        ];
    }
}
