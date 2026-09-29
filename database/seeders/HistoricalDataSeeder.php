<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Assets\PlaceAssetInService;
use App\Actions\Assets\RunMonthlyDepreciation;
use App\Actions\Banking\RecordBankCharge;
use App\Actions\Banking\RecordDeposit;
use App\Actions\Ledger\OpenFiscalYear;
use App\Actions\Ledger\PostJournalEntry;
use App\Actions\Payables\PayBill;
use App\Actions\Payables\PostBill;
use App\Actions\Receivables\PostInvoice;
use App\Actions\Receivables\ReceiveCustomerPayment;
use App\Actions\Tax\AllocateCommonInputVat;
use App\Data\Banking\BankChargeData;
use App\Data\Banking\DepositData;
use App\Data\Ledger\JournalEntryData;
use App\Data\Payables\BillData;
use App\Data\Payables\PayBillData;
use App\Data\Receivables\CustomerPaymentData;
use App\Data\Receivables\InvoiceData;
use App\Enums\AccountSubtype;
use App\Enums\AssetStatus;
use App\Enums\CompanyRole;
use App\Enums\PaymentMethod;
use App\Enums\PosZReadingStatus;
use App\Enums\RecurringKind;
use App\Enums\TaxReturnType;
use App\Enums\VatBucket;
use App\Exceptions\Ledger\DuplicateAllocationException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Bill;
use App\Models\Budget;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\PosZReading;
use App\Models\RecurringTemplate;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Models\WithholdingCode;
use App\Services\Tax\TaxReturnService;
use App\Support\CompanyContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Three years of plausible trading for the demo company (Dari Ventures Corp.),
 * October 2023 up to today, so reports, aging and the dashboard have history
 * to show. Everything posts through the real Actions, so the ledger, VAT
 * buckets, withholding, weighted-average COGS and numbering stay consistent.
 *
 * Deterministic (fixed random seed) and refuses to run twice. Fiscal years are
 * left open so each year's P&L stays visible. Creates the demo company first
 * when it does not exist yet.
 *
 *   php artisan db:seed --class=HistoricalDataSeeder
 */
final class HistoricalDataSeeder extends Seeder
{
    private const START = '2023-10-01';

    private const SEED = 20231001;

    private Company $company;

    private ?User $actor = null;

    private CarbonImmutable $today;

    /** @var array<int|string, int> account code => id */
    private array $accounts = [];

    /** @var array<string, int> tax or withholding code => id */
    private array $codes = [];

    private int $riceItem;

    private int $posItem;

    /** @var array<string, Vendor> */
    private array $vendors = [];

    /** @var list<array{customer: Customer, slow: bool}> */
    private array $riceBuyers = [];

    /** @var list<array{customer: Customer, slow: bool}> */
    private array $posClients = [];

    /** Simulated stock on hand, so sales never outrun purchases. */
    private int $riceStock = 0;

    private int $posStock = 0;

    /** Collected into Cash on Hand and not yet deposited (centavos). */
    private int $undeposited = 0;

    /** Last payroll's amounts still to remit (centavos). */
    private int $payrollWithholding = 0;

    private int $payrollStatutory = 0;

    private int $checkNo = 4100;

    private int $assetNo = 0;

    /** @var array<int, list<array{0: int, 1: Closure(CarbonImmutable): mixed}>> day => [priority, run] */
    private array $events = [];

    /** @var array<string, list<array{0: string, 1: int}>> date => [kind, document id] */
    private array $due = [];

    public function run(): void
    {
        mt_srand(self::SEED);
        $this->today = CarbonImmutable::today();

        $this->company = Company::query()->where('name', 'Dari Ventures Corp.')->first()
            ?? app(DemoCompanySeeder::class)->build();
        app(CompanyContext::class)->set($this->company->id);

        if (Invoice::query()->whereDate('invoice_date', '<', '2026-01-01')->exists()) {
            $this->command->warn('Historical data is already seeded for this company; skipping.');

            return;
        }

        $before = $this->counts();

        DB::transaction(function (): void {
            $this->prepare();

            for ($month = CarbonImmutable::parse(self::START); $month->lessThanOrEqualTo($this->today); $month = $month->addMonth()) {
                $this->runMonth($month);
            }

            $this->addBudgets();
            $this->addTaxReturns();
            $this->addPosInbox();
            $this->addRecurringTemplates();
        });

        foreach ($this->counts() as $label => $count) {
            $this->command->line(sprintf('  %-18s +%d', $label, $count - $before[$label]));
        }
    }

    private function prepare(): void
    {
        foreach ([2023, 2024, 2025, 2026] as $year) {
            app(OpenFiscalYear::class)->handle($this->company, $year);
        }

        $this->actor = $this->company->users()->wherePivot('role', CompanyRole::Owner->value)->first();

        Account::query()->firstOrCreate(['company_id' => $this->company->id, 'code' => '6700'], [
            'name' => 'Bank Service Charges',
            'type' => AccountSubtype::Expense->type(),
            'subtype' => AccountSubtype::Expense,
            'normal_balance' => AccountSubtype::Expense->normalBalance(),
            'is_system' => false,
            'is_active' => true,
        ]);

        foreach (Account::query()->get(['id', 'code']) as $account) {
            $this->accounts[$account->code] = $account->id;
        }
        foreach (TaxCode::query()->get(['id', 'code']) as $code) {
            $this->codes[$code->code] = $code->id;
        }
        foreach (WithholdingCode::query()->get(['id', 'code']) as $code) {
            $this->codes[$code->code] = $code->id;
        }

        $this->riceItem = (int) (Item::query()->where('sku', 'RICE-25')->value('id')
            ?? throw new RuntimeException('Demo item RICE-25 is missing.'));
        $this->posItem = (int) (Item::query()->where('sku', 'POS-T1')->value('id')
            ?? throw new RuntimeException('Demo item POS-T1 is missing.'));

        $this->vendors = [
            'rice_trader' => $this->vendor('Rice Trader'),
            'rice_mill' => $this->vendor('Isabela Rice Mill Corp.'),
            'rice_coop' => $this->vendor('Nueva Ecija Farmers Cooperative', vatRegistered: false),
            'pos_supplier' => $this->vendor('POS Supplier'),
            'tech_hub' => $this->vendor('TechHub Distribution Inc.'),
            'landlord' => $this->vendor('Landlord', 'WC100'),
            'electric' => $this->vendor('Luzon Electric Cooperative'),
            'telecom' => $this->vendor('FiberNet Telecom Corp.'),
            'cpa' => $this->vendor('Santos & Reyes CPAs', 'WI010'),
            'trucking' => $this->vendor('Bilis Trucking Services', 'WC160'),
            'supplies' => $this->vendor('OfficeMate Supplies', 'WC158'),
            'motors' => $this->vendor('AutoHub Motors Corp.'),
            'computers' => $this->vendor('CompuWorld IT Center'),
            'equipment' => $this->vendor('LiftPro Equipment Sales'),
        ];

        $riceBuyers = [
            'Rice Buyer' => false, 'Golden Harvest Grocery' => false, 'Bayanihan Consumers Cooperative' => false,
            'Kusina ni Lola Restaurant' => false, 'San Isidro Wholesale Mart' => false,
            'Tindahan ni Aling Rosa' => true, 'Northpoint Supermarket' => false, 'Masagana Food Services' => false,
        ];
        foreach ($riceBuyers as $name => $slow) {
            $this->riceBuyers[] = ['customer' => $this->customer($name, 15), 'slow' => $slow];
        }

        $posClients = [
            'POS Client' => false, "Kape't Kwentuhan Café" => false, 'BotikaPlus Pharmacy' => false,
            'Luzon Hardware Depot' => false, 'Pinoy Burger Stop' => true, 'Sunrise Bakeshop' => false,
        ];
        foreach ($posClients as $name => $slow) {
            $this->posClients[] = ['customer' => $this->customer($name, 30), 'slow' => $slow];
        }
    }

    private function runMonth(CarbonImmutable $month): void
    {
        $this->events = [];
        $g = $this->growth($month);
        $label = $month->format('F Y');
        $ym = $month->format('Y-m');

        if ($ym === '2023-10') {
            $this->on(1, 0, fn (CarbonImmutable $d) => $this->journal($d, 'Initial share capital', [['1120', 3_500_000_00], ['3100', -3_500_000_00]]));
            $this->on(5, 1, fn (CarbonImmutable $d) => $this->buyAsset($d, 'motors', 'Vehicles', '6-wheeler delivery truck', 1_150_000_00, 150_000_00, 60));
            $this->on(9, 1, fn (CarbonImmutable $d) => $this->buyAsset($d, 'computers', 'Office & IT Equipment', 'Laptops, printers and network gear', 180_000_00, 0, 36));
        }
        if ($ym === '2025-01') {
            $this->on(14, 1, fn (CarbonImmutable $d) => $this->buyAsset($d, 'equipment', 'Warehouse Equipment', 'Warehouse forklift (2.5 t)', 650_000_00, 50_000_00, 60));
        }

        // Purchases. The June 2026 golden-master fixture already carries that month's rent.
        if ($ym !== '2026-06') {
            $this->on(1, 1, fn (CarbonImmutable $d) => $this->bill($d, 'landlord', "Office and warehouse rent — {$label}", '6100', $this->rent($month), VatBucket::Common, inclusive: true, payInDays: 4));
        }
        $this->on(3, 1, fn (CarbonImmutable $d) => $this->buyRice($d, $g));
        $this->on(6, 1, fn (CarbonImmutable $d) => $this->buyPos($d, $g));
        $this->on(17, 1, fn (CarbonImmutable $d) => $this->buyRice($d, $g));
        $this->on(20, 1, fn (CarbonImmutable $d) => $this->bill($d, 'electric', "Electricity — {$label}", '6200', $this->electricity($month, $g), VatBucket::Common, inclusive: true, payInDays: 12));
        $this->on(22, 1, fn (CarbonImmutable $d) => $this->bill($d, 'telecom', "Fiber internet — {$label}", '6200', 3_499_00, VatBucket::Common, inclusive: true, payInDays: 10));
        $this->on(25, 1, fn (CarbonImmutable $d) => $this->bill($d, 'trucking', "Rice deliveries — {$label}", '6600', $this->pesos(7_000, 14_000, $g), VatBucket::Common, payInDays: mt_rand(15, 35)));
        if ($month->month % 2 === 0) {
            $this->on(12, 1, fn (CarbonImmutable $d) => $this->bill($d, 'supplies', 'Office supplies', '6400', $this->pesos(1_500, 6_000, $g), VatBucket::Common, payInDays: mt_rand(10, 30)));
        }
        if (in_array($month->month, [1, 4, 7, 10], true)) {
            $this->on(15, 1, fn (CarbonImmutable $d) => $this->bill($d, 'cpa', "Bookkeeping and tax retainer — Q{$month->quarter} {$month->year}", '6500', 30_000_00, VatBucket::Common, payInDays: 30));
        }

        // Sales: rice (VAT-exempt), POS terminals (VATable) and POS services.
        $riceSales = mt_rand(7, 10) + ($month->month === 12 ? 2 : 0);
        for ($i = 0; $i < $riceSales; $i++) {
            $this->on(mt_rand(2, 28), 2, fn (CarbonImmutable $d) => $this->sellRice($d, $g));
        }
        for ($i = mt_rand(2, 4); $i > 0; $i--) {
            $this->on(mt_rand(3, 27), 2, fn (CarbonImmutable $d) => $this->sellPos($d, $g));
        }
        for ($i = mt_rand(1, 3); $i > 0; $i--) {
            $this->on(mt_rand(3, 27), 2, fn (CarbonImmutable $d) => $this->sellService($d, $g));
        }

        // Payroll at month end; last month's taxes and EWT remitted on the 10th.
        $this->on($month->daysInMonth, 4, fn (CarbonImmutable $d) => $this->payroll($d));
        if ($month->month === 12) {
            $this->on(15, 4, fn (CarbonImmutable $d) => $this->thirteenthMonth($d));
        }
        if ($ym !== '2023-10') {
            $this->on(10, 4, fn (CarbonImmutable $d) => $this->remitPayrollTaxes($d));
            $this->on(10, 4, fn (CarbonImmutable $d) => $this->remitEwt($d));
        }

        // Quarter close: allocate common input VAT, then remit the VAT due.
        if (in_array($month->month, [1, 4, 7, 10], true) && $ym !== '2023-10') {
            $quarterEnd = $month->subDay();
            $this->on(1, 0, fn () => $this->allocateInputVat($quarterEnd));
            $this->on(25, 4, fn (CarbonImmutable $d) => $this->remitVat($d, $quarterEnd));
        }

        $this->on(15, 5, fn (CarbonImmutable $d) => $this->depositCash($d));
        $this->on(28, 5, fn (CarbonImmutable $d) => $this->bankCharge($d));
        $this->on($month->daysInMonth, 5, fn (CarbonImmutable $d) => $this->depositCash($d));
        $this->on($month->daysInMonth, 6, fn (CarbonImmutable $d) => $this->depreciate($d));

        for ($day = 1; $day <= $month->daysInMonth; $day++) {
            $date = $month->setDay($day);
            if ($date->greaterThan($this->today)) {
                return;
            }

            $todo = $this->events[$day] ?? [];
            foreach ($this->due[$date->toDateString()] ?? [] as [$kind, $id]) {
                $todo[] = [3, fn (CarbonImmutable $d) => $this->settle($kind, $id, $d)];
            }
            unset($this->due[$date->toDateString()]);

            usort($todo, fn (array $a, array $b): int => $a[0] <=> $b[0]);
            foreach ($todo as [, $run]) {
                $run($date);
            }
        }
    }

    /**
     * @param  Closure(CarbonImmutable): mixed  $run
     */
    private function on(int $day, int $priority, Closure $run): void
    {
        $this->events[$day][] = [$priority, $run];
    }

    private function schedule(CarbonImmutable $date, string $kind, int $id): void
    {
        $this->due[$date->toDateString()][] = [$kind, $id];
    }

    // --- Purchases -------------------------------------------------------

    private function buyRice(CarbonImmutable $date, float $g): void
    {
        // Restock to about three weeks of demand (~600 sacks a month, growing),
        // so stock turns over instead of piling up.
        $qty = (int) round(600 * $g * 0.7 * mt_rand(95, 105) / 100) - $this->riceStock;
        if ($qty < 40) {
            return;
        }

        $cost = $this->pesos(1_820, 1_900) + (int) round($this->yearsIn($date) * 70) * 100;
        $vendor = ['rice_trader', 'rice_mill', 'rice_coop'][mt_rand(0, 2)];

        $this->bill($date, $vendor, 'Rice 25kg', '1300', $cost, null, qty: (string) $qty, itemId: $this->riceItem, tax: 'EXEMPT', payInDays: mt_rand(7, 30));
        $this->riceStock += $qty;
    }

    private function buyPos(CarbonImmutable $date, float $g): void
    {
        $qty = max(0, (int) round(4 * $g) + 2 - $this->posStock) + mt_rand(0, 2);
        if ($qty === 0) {
            return;
        }

        $cost = $this->pesos(19_200, 19_800) + (int) round($this->yearsIn($date) * 500) * 100;
        $vendor = mt_rand(0, 1) === 0 ? 'pos_supplier' : 'tech_hub';

        $this->bill($date, $vendor, 'POS terminal (touchscreen, printer, cash drawer)', '1310', $cost, VatBucket::DirectVatable, qty: (string) $qty, itemId: $this->posItem, payInDays: mt_rand(20, 40));
        $this->posStock += $qty;
    }

    private function buyAsset(CarbonImmutable $date, string $vendor, string $category, string $name, int $cost, int $salvage, int $lifeMonths): void
    {
        $this->bill($date, $vendor, $name, '1500', $cost, VatBucket::Common, payInDays: 30);

        $assetCategory = AssetCategory::query()->where('name', $category)->first()
            ?? AssetCategory::factory()->create([
                'company_id' => $this->company->id,
                'name' => $category,
                'fixed_asset_account_id' => $this->accounts['1500'],
                'accum_depreciation_account_id' => $this->accounts['1510'],
                'depreciation_expense_account_id' => $this->accounts['6800'],
                'default_useful_life_months' => $lifeMonths,
            ]);

        $asset = Asset::factory()->create([
            'company_id' => $this->company->id,
            'asset_category_id' => $assetCategory->id,
            'number' => sprintf('FA-%04d', ++$this->assetNo),
            'name' => $name,
            'acquisition_date' => $date->toDateString(),
            'acquisition_cost' => $cost,
            'salvage_value' => $salvage,
            'useful_life_months' => $lifeMonths,
            'status' => AssetStatus::Draft,
        ]);

        app(PlaceAssetInService::class)->handle($asset, $date->toDateString());
    }

    private function bill(
        CarbonImmutable $date,
        string $vendor,
        string $description,
        string $account,
        int $unitPrice,
        ?VatBucket $bucket,
        bool $inclusive = false,
        int $payInDays = 30,
        string $qty = '1',
        ?int $itemId = null,
        string $tax = 'VAT12',
    ): Bill {
        $bill = app(PostBill::class)->handle(BillData::from([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendors[$vendor]->id,
            'bill_date' => $date->toDateString(),
            'due_date' => $date->addDays(30)->toDateString(),
            'pricing_mode' => $inclusive ? 'vat_inclusive' : 'vat_exclusive',
            'lines' => [[
                'description' => $description,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'tax_code_id' => $this->codes[$tax],
                'vat_bucket' => $bucket?->value,
                'item_id' => $itemId,
                'expense_or_asset_account_id' => $this->accounts[$account],
            ]],
        ]), $this->actor);

        $this->schedule($date->addDays($payInDays), 'pay', $bill->id);

        return $bill;
    }

    // --- Sales -----------------------------------------------------------

    private function sellRice(CarbonImmutable $date, float $g): void
    {
        $qty = min((int) round(mt_rand(30, 110) * $g), $this->riceStock);
        if ($qty < 10) {
            return;
        }

        ['customer' => $customer, 'slow' => $slow] = $this->riceBuyers[mt_rand(0, count($this->riceBuyers) - 1)];
        $price = $this->pesos(2_330, 2_420) + (int) round($this->yearsIn($date) * 90) * 100;

        $invoice = $this->invoice($date, $customer, 'Rice 25kg', (string) $qty, $price, 'EXEMPT', '4100', $this->riceItem);
        $this->riceStock -= $qty;
        $this->collectLater($invoice, $customer, $date, $slow, 'collect_retail');
    }

    private function sellPos(CarbonImmutable $date, float $g): void
    {
        $qty = min(mt_rand(1, 2), $this->posStock);
        if ($qty === 0) {
            return;
        }

        ['customer' => $customer, 'slow' => $slow] = $this->posClients[mt_rand(0, count($this->posClients) - 1)];
        $price = $this->pesos(54_000, 57_000) + (int) round($this->yearsIn($date) * 1_000) * 100;

        $invoice = $this->invoice($date, $customer, 'POS terminal — installed', (string) $qty, $price, 'VAT12', '4200', $this->posItem);
        $this->posStock -= $qty;
        $this->collectLater($invoice, $customer, $date, $slow, 'collect');
    }

    private function sellService(CarbonImmutable $date, float $g): void
    {
        ['customer' => $customer, 'slow' => $slow] = $this->posClients[mt_rand(0, count($this->posClients) - 1)];
        $description = ['POS support plan (quarterly)', 'On-site repair and servicing', 'Menu and inventory setup', 'Staff training'][mt_rand(0, 3)];

        $invoice = $this->invoice($date, $customer, $description, '1', $this->pesos(4_500, 18_000, $g), 'VAT12', '4300');
        $this->collectLater($invoice, $customer, $date, $slow, 'collect');
    }

    private function invoice(CarbonImmutable $date, Customer $customer, string $description, string $qty, int $unitPrice, string $tax, string $account, ?int $itemId = null): Invoice
    {
        return app(PostInvoice::class)->handle(InvoiceData::from([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'invoice_date' => $date->toDateString(),
            'due_date' => $date->addDays($customer->terms_days)->toDateString(),
            'lines' => [[
                'description' => $description,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'tax_code_id' => $this->codes[$tax],
                'income_account_id' => $this->accounts[$account],
                'item_id' => $itemId,
            ]],
        ]), $this->actor);
    }

    /** Most customers pay within terms; some pay late and a rare few never do. */
    private function collectLater(Invoice $invoice, Customer $customer, CarbonImmutable $date, bool $slow, string $kind): void
    {
        $roll = mt_rand(1, 1000);

        $days = match (true) {
            $roll <= 6 => null,
            $slow && $roll <= 600, $roll <= 80 => mt_rand(60, 150),
            default => mt_rand(5, $customer->terms_days + 10),
        };

        if ($days !== null) {
            $this->schedule($date->addDays($days), $kind, $invoice->id);
        }
    }

    // --- Settlements -----------------------------------------------------

    private function settle(string $kind, int $id, CarbonImmutable $date): void
    {
        if ($kind === 'pay') {
            $bill = Bill::query()->findOrFail($id);
            $amount = $bill->outstanding();
            if ($amount > 0) {
                app(PayBill::class)->handle(PayBillData::from([
                    'company_id' => $this->company->id,
                    'vendor_id' => $bill->vendor_id,
                    'payment_date' => $date->toDateString(),
                    'paid_from_account_id' => $this->accounts['1120'],
                    'method' => PaymentMethod::Check->value,
                    'external_reference_no' => 'CHK-'.(++$this->checkNo),
                    'applications' => [['bill_id' => $bill->id, 'amount' => $amount]],
                ]), $this->actor);
            }

            return;
        }

        $invoice = Invoice::query()->findOrFail($id);
        $amount = $invoice->outstanding();
        if ($amount <= 0) {
            return;
        }

        // Retail rice buyers often pay cash, which is banked twice a month.
        $cash = $kind === 'collect_retail' && mt_rand(1, 100) <= 35;
        $method = $cash ? PaymentMethod::Cash : [PaymentMethod::Bank, PaymentMethod::Check, PaymentMethod::Gcash][mt_rand(0, 2)];

        app(ReceiveCustomerPayment::class)->handle(CustomerPaymentData::from([
            'company_id' => $this->company->id,
            'customer_id' => $invoice->customer_id,
            'payment_date' => $date->toDateString(),
            'deposit_to_account_id' => $this->accounts[$cash ? '1110' : '1120'],
            'amount' => $amount,
            'method' => $method->value,
            'applications' => [['invoice_id' => $invoice->id, 'amount' => $amount]],
        ]), $this->actor);

        if ($cash) {
            $this->undeposited += $amount;
        }
    }

    // --- Payroll, taxes and banking -------------------------------------

    private function payroll(CarbonImmutable $date): void
    {
        $gross = $this->monthlyPayroll($date);
        $employee = (int) round($gross * 0.045);
        $employer = (int) round($gross * 0.095);
        $withholding = (int) round($gross * 0.06);

        $this->journal($date, 'Payroll — '.$date->format('F Y'), [
            ['6300', $gross],
            ['6310', $employer],
            ['2220', -$withholding],
            ['2230', -($employee + $employer)],
            ['1120', -($gross - $withholding - $employee)],
        ]);

        $this->payrollWithholding = $withholding;
        $this->payrollStatutory = $employee + $employer;
    }

    private function thirteenthMonth(CarbonImmutable $date): void
    {
        $amount = $this->monthlyPayroll($date);

        $this->journal($date, '13th-month pay '.$date->year, [['6300', $amount], ['1120', -$amount]]);
    }

    private function monthlyPayroll(CarbonImmutable $date): int
    {
        $headcount = match (true) {
            $date->year >= 2026 => 9,
            $date->year === 2025 => 8,
            default => 6,
        };

        return (int) round($headcount * 26_000 * (1 + 0.05 * $this->yearsIn($date))) * 100;
    }

    private function remitPayrollTaxes(CarbonImmutable $date): void
    {
        if ($this->payrollWithholding + $this->payrollStatutory === 0) {
            return;
        }

        $this->journal($date, 'Remit compensation withholding and SSS/PhilHealth/Pag-IBIG', [
            ['2220', $this->payrollWithholding],
            ['2230', $this->payrollStatutory],
            ['1120', -($this->payrollWithholding + $this->payrollStatutory)],
        ]);

        $this->payrollWithholding = $this->payrollStatutory = 0;
    }

    private function remitEwt(CarbonImmutable $date): void
    {
        $withheld = $this->creditBalance('2210', $date->subMonthNoOverflow()->endOfMonth());
        if ($withheld > 0) {
            $this->journal($date, 'Remit expanded withholding tax (0619-E)', [['2210', $withheld], ['1120', -$withheld]]);
        }
    }

    private function allocateInputVat(CarbonImmutable $quarterEnd): void
    {
        try {
            app(AllocateCommonInputVat::class)->handle($this->company, $quarterEnd->year, $quarterEnd->quarter, $this->actor);
        } catch (DuplicateAllocationException) {
            // Already allocated (the June 2026 golden-master fixture allocates Q2 2026).
        }
    }

    private function remitVat(CarbonImmutable $date, CarbonImmutable $quarterEnd): void
    {
        $output = $this->creditBalance('2200', $quarterEnd);
        if ($output <= 0) {
            return;
        }

        // Input VAT offsets output VAT; any excess carries over to the next quarter.
        $creditable = min(-$this->creditBalance('1400', $quarterEnd), $output);
        $lines = [['2200', $output], ['1400', -$creditable]];
        if ($output > $creditable) {
            $lines[] = ['1120', -($output - $creditable)];
        }

        $this->journal($date, "VAT remittance (2550Q) — Q{$quarterEnd->quarter} {$quarterEnd->year}", $lines);
    }

    private function depositCash(CarbonImmutable $date): void
    {
        if ($this->undeposited === 0) {
            return;
        }

        app(RecordDeposit::class)->handle(DepositData::from([
            'company_id' => $this->company->id,
            'bank_account_id' => $this->accounts['1120'],
            'source_account_id' => $this->accounts['1110'],
            'date' => $date->toDateString(),
            'amount' => $this->undeposited,
            'memo' => 'Deposit of cash collections',
        ]), $this->actor);

        $this->undeposited = 0;
    }

    private function bankCharge(CarbonImmutable $date): void
    {
        app(RecordBankCharge::class)->handle(BankChargeData::from([
            'company_id' => $this->company->id,
            'bank_account_id' => $this->accounts['1120'],
            'expense_account_id' => $this->accounts['6700'],
            'date' => $date->toDateString(),
            'amount' => $this->pesos(150, 450),
            'memo' => 'Monthly bank service charge',
        ]), $this->actor);
    }

    private function depreciate(CarbonImmutable $date): void
    {
        $period = AccountingPeriod::query()->containing($date->toDateString())->firstOrFail();

        app(RunMonthlyDepreciation::class)->handle($this->company, $period, $this->actor);
    }

    // --- Extras: budgets, tax returns, POS inbox, recurring templates ----

    private function addBudgets(): void
    {
        $codes = ['4100', '4200', '4300', '5100', '5200', '6100', '6200', '6300', '6310', '6400', '6500', '6600', '6800'];

        foreach ([2024, 2025, 2026] as $year) {
            $to = $year === $this->today->year ? $this->today : CarbonImmutable::create($year, 12, 31);
            $budget = Budget::query()->create([
                'company_id' => $this->company->id,
                'fiscal_year' => $year,
                'name' => 'Operating budget',
                'status' => 'active',
                'created_by' => $this->actor?->id,
            ]);

            foreach ($codes as $code) {
                $actual = $this->naturalMovement($code, "{$year}-01-01", $to->toDateString());
                // Annualised actuals ± a planning variance, to the nearest thousand pesos.
                $annual = (int) round($actual * 12 / $to->month * mt_rand(92, 110) / 100 / 100_000) * 100_000;
                if ($annual > 0) {
                    $budget->lines()->create(['account_id' => $this->accounts[$code], 'amount' => $annual]);
                }
            }
        }
    }

    private function addTaxReturns(): void
    {
        $service = app(TaxReturnService::class);

        for ($quarterEnd = CarbonImmutable::parse('2023-12-31'); $quarterEnd->lessThan($this->today); $quarterEnd = $quarterEnd->addMonthsNoOverflow(3)->endOfMonth()) {
            foreach ([TaxReturnType::Vat2550Q, TaxReturnType::Ewt1601EQ] as $type) {
                $service->prepare($this->company, $type, $quarterEnd->year, $quarterEnd->quarter, $this->actor?->id);
            }
        }
    }

    /** A week of POS Z-readings waiting in the integration inbox. */
    private function addPosInbox(): void
    {
        for ($daysAgo = 7; $daysAgo >= 2; $daysAgo--) {
            $day = $this->today->subDays($daysAgo);
            $vatable = $this->pesos(35_000, 80_000);
            $exempt = $this->pesos(50_000, 120_000);
            $vat = (int) round($vatable * 0.12);
            $discounts = $this->pesos(0, 3_000);
            $cash = (int) round(($vatable + $vat + $exempt - $discounts) * mt_rand(45, 70) / 100);

            PosZReading::factory()->create([
                'company_id' => $this->company->id,
                'business_date' => $day->toDateString(),
                'reference' => 'Z-'.$day->format('ymd'),
                'vatable_sales' => $vatable,
                'exempt_sales' => $exempt,
                'zero_rated_sales' => 0,
                'vat_amount' => $vat,
                'discounts' => $discounts,
                'tenders' => ['cash' => $cash, 'card' => $vatable + $vat + $exempt - $discounts - $cash],
                'status' => PosZReadingStatus::Pending,
            ]);
        }
    }

    private function addRecurringTemplates(): void
    {
        $next = $this->today->addMonthNoOverflow()->startOfMonth();

        RecurringTemplate::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Petty cash replenishment',
            'kind' => RecurringKind::JournalEntry,
            'payload' => ['memo' => 'Petty cash replenishment', 'lines' => [
                ['account_id' => $this->accounts['6400'], 'debit' => 5_000_00],
                ['account_id' => $this->accounts['1110'], 'credit' => 5_000_00],
            ]],
            'starts_on' => $next->toDateString(),
            'next_run_on' => $next->toDateString(),
            'auto_post' => false,
            'created_by' => $this->actor?->id,
            'updated_by' => $this->actor?->id,
        ]);

        RecurringTemplate::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Monthly depreciation',
            'kind' => RecurringKind::DepreciationRun,
            'payload' => null,
            'day_of_month' => 28,
            'starts_on' => $next->endOfMonth()->toDateString(),
            'next_run_on' => $next->endOfMonth()->toDateString(),
            'auto_post' => true,
            'created_by' => $this->actor?->id,
            'updated_by' => $this->actor?->id,
        ]);
    }

    // --- Helpers -----------------------------------------------------------

    /**
     * @param  list<array{0: string, 1: int}>  $lines  [account code, signed amount: + debit / − credit]
     */
    private function journal(CarbonImmutable $date, string $memo, array $lines): JournalEntry
    {
        return app(PostJournalEntry::class)->handle(JournalEntryData::from([
            'company_id' => $this->company->id,
            'entry_date' => $date->toDateString(),
            'memo' => $memo,
            'lines' => array_map(fn (array $line): array => [
                'account_id' => $this->accounts[$line[0]],
                $line[1] >= 0 ? 'debit' : 'credit' => abs($line[1]),
            ], $lines),
        ]), $this->actor);
    }

    /** Credits minus debits on an account up to a date (centavos). */
    private function creditBalance(string $code, CarbonImmutable $asOf): int
    {
        return (int) DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $this->company->id)
            ->whereIn('journal_entries.status', ['posted', 'reversed'])
            ->where('journal_lines.account_id', $this->accounts[$code])
            ->whereDate('journal_entries.entry_date', '<=', $asOf->toDateString())
            ->sum(DB::raw('journal_lines.credit - journal_lines.debit'));
    }

    /** Movement in an account's normal direction between two dates (centavos). */
    private function naturalMovement(string $code, string $from, string $to): int
    {
        $credits = (int) DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $this->company->id)
            ->whereIn('journal_entries.status', ['posted', 'reversed'])
            ->where('journal_lines.account_id', $this->accounts[$code])
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->sum(DB::raw('journal_lines.credit - journal_lines.debit'));

        return str_starts_with($code, '4') ? $credits : -$credits;
    }

    private function vendor(string $name, ?string $withholding = null, bool $vatRegistered = true): Vendor
    {
        return Vendor::query()->where('name', $name)->first()
            ?? Vendor::factory()->create([
                'company_id' => $this->company->id,
                'name' => $name,
                'is_vat_registered' => $vatRegistered,
                'default_withholding_code_id' => $withholding !== null ? $this->codes[$withholding] : null,
            ]);
    }

    private function customer(string $name, int $termsDays): Customer
    {
        return Customer::query()->where('name', $name)->first()
            ?? Customer::factory()->create([
                'company_id' => $this->company->id,
                'name' => $name,
                'terms_days' => $termsDays,
            ]);
    }

    /** A random whole-peso amount in centavos, optionally scaled. */
    private function pesos(int $min, int $max, float $scale = 1.0): int
    {
        return (int) round(mt_rand($min, $max) * $scale) * 100;
    }

    /** Business growth: about 8% a year from the start of the history. */
    private function growth(CarbonImmutable $date): float
    {
        return 1 + 0.08 * $this->yearsIn($date);
    }

    private function yearsIn(CarbonImmutable $date): float
    {
        return max(0.0, CarbonImmutable::parse(self::START)->diffInDays($date) / 365);
    }

    private function rent(CarbonImmutable $month): int
    {
        return match (true) {
            $month->year >= 2026 => 63_000_00,
            $month->year === 2025 => 60_000_00,
            default => 56_000_00,
        };
    }

    /** Electricity peaks in the hot months (April–June). */
    private function electricity(CarbonImmutable $month, float $g): int
    {
        $base = in_array($month->month, [4, 5, 6], true) ? 26_000 : 18_000;

        return $this->pesos((int) ($base * 0.9), (int) ($base * 1.15), $g);
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'invoices' => Invoice::query()->count(),
            'bills' => Bill::query()->count(),
            'receipts' => CustomerPayment::query()->count(),
            'bill payments' => VendorPayment::query()->count(),
            'journal entries' => JournalEntry::query()->count(),
        ];
    }
}
