<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
final class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'vendor_id' => Vendor::factory(),
            'order_date' => '2026-03-01',
            'status' => 'draft',
            'pricing_mode' => 'vat_exclusive',
        ];
    }
}
