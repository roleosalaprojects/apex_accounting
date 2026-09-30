<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\SalesOrder;
use App\Services\Printing\PrintOrder;

/** A quotation while the order is draft or sent, a sales order once accepted. */
final class SalesOrderMail extends DocumentMail
{
    public function __construct(public readonly SalesOrder $order, string $message)
    {
        parent::__construct($message);
        $this->order->loadMissing(['company', 'customer', 'lines']);
    }

    public static function kind(SalesOrder $order): string
    {
        return in_array($order->status, ['draft', 'sent'], true) ? 'Quotation' : 'Sales Order';
    }

    public static function defaultSubject(SalesOrder $order): string
    {
        $order->loadMissing('company');

        return self::kind($order)." {$order->number} from {$order->company->name}";
    }

    public static function defaultMessage(SalesOrder $order): string
    {
        $order->loadMissing(['company', 'customer']);
        $kind = strtolower(self::kind($order));

        return 'Dear '.($order->customer->contact_person ?: $order->customer->name).",\n\n"
            ."Please find attached our {$kind} {$order->number} dated {$order->order_date->format('M j, Y')}"
            .($order->expiry_date && $kind === 'quotation' ? ", valid until {$order->expiry_date->format('M j, Y')}" : '').".\n\n"
            ."We look forward to your confirmation.\n\n{$order->company->name}";
    }

    public function company(): Company
    {
        return $this->order->company;
    }

    public function partyName(): string
    {
        return $this->order->customer->name;
    }

    protected function subjectLine(): string
    {
        return self::defaultSubject($this->order);
    }

    protected function facts(): array
    {
        return array_filter([
            self::kind($this->order) => (string) $this->order->number,
            'Date' => $this->order->order_date->format('M j, Y'),
            'Valid until' => self::kind($this->order) === 'Quotation' ? $this->order->expiry_date?->format('M j, Y') : null,
            'Total' => $this->peso($this->order->subtotal()),
        ]);
    }

    /**
     * @return array{name: string, pdf: string}
     */
    protected function pdf(): array
    {
        return ['name' => "{$this->order->number}.pdf", 'pdf' => app(PrintOrder::class)->salesOrder($this->order)];
    }
}
