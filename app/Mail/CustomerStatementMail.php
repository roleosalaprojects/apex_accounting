<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\Customer;
use App\Services\Printing\PrintCustomerStatement;
use App\Services\Reports\StatementOfAccount;
use Illuminate\Support\Carbon;

final class CustomerStatementMail extends DocumentMail
{
    public function __construct(public readonly Customer $customer, public readonly string $periodFrom, public readonly string $periodTo, string $message)
    {
        parent::__construct($message);
        $this->customer->loadMissing('company');
    }

    public static function defaultSubject(Customer $customer, string $asOf): string
    {
        $customer->loadMissing('company');

        return 'Statement of Account as of '.Carbon::parse($asOf)->format('M j, Y')." from {$customer->company->name}";
    }

    public static function defaultMessage(Customer $customer, string $from, string $asOf): string
    {
        $customer->loadMissing('company');

        return 'Dear '.($customer->contact_person ?: $customer->name).",\n\n"
            .'Please find attached your Statement of Account for '.Carbon::parse($from)->format('M j, Y').' to '.Carbon::parse($asOf)->format('M j, Y').".\n\n"
            ."Kindly let us know of any difference against your records. If payment has already been made, please disregard this statement.\n\n{$customer->company->name}";
    }

    public function company(): Company
    {
        return $this->customer->company;
    }

    public function partyName(): string
    {
        return $this->customer->name;
    }

    protected function subjectLine(): string
    {
        return self::defaultSubject($this->customer, $this->periodTo);
    }

    protected function facts(): array
    {
        $statement = app(StatementOfAccount::class)->build($this->customer, $this->periodFrom, $this->periodTo);

        return [
            'Period' => Carbon::parse($this->periodFrom)->format('M j, Y').' – '.Carbon::parse($this->periodTo)->format('M j, Y'),
            'Balance due' => $this->peso($statement['closing']),
        ];
    }

    /**
     * @return array{name: string, pdf: string}
     */
    protected function pdf(): array
    {
        return [
            'name' => 'SOA-'.$this->customer->code.'-'.Carbon::parse($this->periodTo)->format('Ymd').'.pdf',
            'pdf' => app(PrintCustomerStatement::class)->render($this->customer, $this->periodFrom, $this->periodTo),
        ];
    }
}
