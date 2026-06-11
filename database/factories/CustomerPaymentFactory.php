<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPayment>
 */
final class CustomerPaymentFactory extends Factory
{
    protected $model = CustomerPayment::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'payment_date' => '2026-06-15',
            'method' => PaymentMethod::Bank,
            'deposit_to_account_id' => 1,
            'amount' => 0,
            'status' => 'posted',
        ];
    }
}
