<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;

/** Labels and badge colours for document statuses and payment methods in tables. */
final class DocumentStatus
{
    public static function label(InvoiceStatus $status): string
    {
        return ucfirst(str_replace('_', ' ', $status->value));
    }

    public static function color(InvoiceStatus $status): string
    {
        return match ($status) {
            InvoiceStatus::Paid => 'success',
            InvoiceStatus::PartiallyPaid => 'warning',
            InvoiceStatus::Posted => 'info',
            default => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (InvoiceStatus::cases() as $status) {
            $options[$status->value] = self::label($status);
        }

        return $options;
    }

    public static function method(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::Gcash => 'GCash',
            default => ucfirst($method->value),
        };
    }
}
