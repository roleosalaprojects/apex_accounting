<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Services\Printing\PrintOrder;

final class PurchaseOrderMail extends DocumentMail
{
    public function __construct(public readonly PurchaseOrder $order, string $message)
    {
        parent::__construct($message);
        $this->order->loadMissing(['company', 'vendor', 'lines']);
    }

    public static function defaultSubject(PurchaseOrder $order): string
    {
        $order->loadMissing('company');

        return "Purchase Order {$order->number} from {$order->company->name}";
    }

    public static function defaultMessage(PurchaseOrder $order): string
    {
        $order->loadMissing(['company', 'vendor']);

        return 'Dear '.($order->vendor->contact_person ?: $order->vendor->name).",\n\n"
            ."Please find attached our Purchase Order {$order->number} dated {$order->order_date->format('M j, Y')}"
            .($order->expected_date ? ", for delivery by {$order->expected_date->format('M j, Y')}" : '').".\n\n"
            ."Kindly confirm receipt and your delivery schedule.\n\n{$order->company->name}";
    }

    public function company(): Company
    {
        return $this->order->company;
    }

    public function partyName(): string
    {
        return $this->order->vendor->name;
    }

    protected function subjectLine(): string
    {
        return self::defaultSubject($this->order);
    }

    protected function facts(): array
    {
        return array_filter([
            'Purchase order' => (string) $this->order->number,
            'Date' => $this->order->order_date->format('M j, Y'),
            'Expected' => $this->order->expected_date?->format('M j, Y'),
            'Total' => $this->peso($this->order->subtotal()),
        ]);
    }

    /**
     * @return array{name: string, pdf: string}
     */
    protected function pdf(): array
    {
        return ['name' => "{$this->order->number}.pdf", 'pdf' => app(PrintOrder::class)->purchaseOrder($this->order)];
    }
}
