<?php

declare(strict_types=1);

namespace App\Data\Receivables;

use App\Enums\PaymentMethod;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class CustomerPaymentData extends Data
{
    /**
     * @param  DataCollection<int, PaymentApplicationData>  $applications
     */
    public function __construct(
        public int $company_id,
        public int $customer_id,
        public string $payment_date,
        public int $deposit_to_account_id,
        public int $amount,            // cash received (centavos)
        #[DataCollectionOf(PaymentApplicationData::class)]
        public DataCollection $applications,
        public PaymentMethod $method = PaymentMethod::Cash,
        public int $ewt_withheld = 0,  // creditable withholding tax withheld by the customer
        public ?string $collection_receipt_no = null,
        public ?string $reference_no = null,
        public ?string $external_reference_no = null,
        public ?string $remarks = null,
        public ?int $created_by = null,
        public ?int $approved_by = null,
    ) {}
}
