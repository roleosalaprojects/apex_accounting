<?php

declare(strict_types=1);

namespace App\Data\Payables;

use App\Enums\PaymentMethod;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class PayBillData extends Data
{
    /**
     * @param  DataCollection<int, BillApplicationData>  $applications
     */
    public function __construct(
        public int $company_id,
        public int $vendor_id,
        public string $payment_date,
        public int $paid_from_account_id,
        #[DataCollectionOf(BillApplicationData::class)]
        public DataCollection $applications,
        public PaymentMethod $method = PaymentMethod::Bank,
        public ?int $withholding_code_id = null, // defaults to the vendor's default code
        public ?string $reference_no = null,
        public ?string $external_reference_no = null, // check no.
        public ?string $remarks = null,
        public ?int $created_by = null,
        public ?int $approved_by = null,
    ) {}
}
