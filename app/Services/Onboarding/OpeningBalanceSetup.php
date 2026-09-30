<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Actions\Inventory\AdjustInventory;
use App\Actions\Ledger\OpenFiscalYear;
use App\Actions\Ledger\SetupOpeningBalances;
use App\Actions\Payables\PostBill;
use App\Actions\Receivables\PostInvoice;
use App\Data\Ledger\OpeningBalancesData;
use App\Data\Payables\BillData;
use App\Data\Receivables\InvoiceData;
use App\Enums\AccountType;
use App\Enums\ItemType;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Bill;
use App\Models\Company;
use App\Models\InventoryAdjustment;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\TaxCode;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The guided cutover (§4.1b) in one transaction: the opening trial balance
 * as a journal entry, each customer's open invoices and each vendor's open
 * bills as opening documents, and the stock on hand as an opening count —
 * every piece offset to 3950 Opening Balance Equity, so the ledger, the
 * subledgers and the stock ledger all start from the same picture.
 *
 * The receivable, payable and stocked-inventory accounts are fed by their
 * own steps and refuse a balance typed straight in, which would count the
 * same money twice.
 */
final class OpeningBalanceSetup
{
    public function __construct(
        private readonly SetupOpeningBalances $balances,
        private readonly PostInvoice $postInvoice,
        private readonly PostBill $postBill,
        private readonly AdjustInventory $adjust,
        private readonly OpenFiscalYear $openYear,
    ) {}

    /**
     * @param  array{balances?: array<int, array{debit?: int, credit?: int}>, invoices?: list<array{customer_id: int, number: ?string, date: string, due_date: ?string, amount: int}>, bills?: list<array{vendor_id: int, number: ?string, date: string, due_date: ?string, amount: int}>, stock?: list<array{item_id: int, qty: string, unit_cost: int}>}  $input
     * @return array{entry: JournalEntry|null, invoices: list<Invoice>, bills: list<Bill>, stock: list<InventoryAdjustment>}
     */
    public function handle(Company $company, string $openingDate, array $input, ?User $actor = null): array
    {
        $balances = array_filter($input['balances'] ?? [], fn (array $b): bool => (int) ($b['debit'] ?? 0) !== 0 || (int) ($b['credit'] ?? 0) !== 0);
        $invoices = $input['invoices'] ?? [];
        $bills = $input['bills'] ?? [];
        $stock = $input['stock'] ?? [];

        if ($balances === [] && $invoices === [] && $bills === [] && $stock === []) {
            throw new RuntimeException('There is nothing to post: enter at least one balance, open document or stock line.');
        }
        $this->assertBalancesAreEnterable($company, array_keys($balances));

        return DB::transaction(function () use ($company, $openingDate, $balances, $invoices, $bills, $stock, $actor): array {
            $this->ensurePeriodFor($company, $openingDate);
            $exempt = TaxCode::query()->withoutGlobalScopes()->where('company_id', $company->id)->where('code', 'EXEMPT')->firstOrFail();
            $obe = $this->account($company, '3950');
            $result = ['entry' => null, 'invoices' => [], 'bills' => [], 'stock' => []];

            if ($balances !== []) {
                $result['entry'] = $this->balances->handle(OpeningBalancesData::from([
                    'company_id' => $company->id,
                    'opening_date' => $openingDate,
                    'lines' => array_map(fn (int $accountId, array $b): array => [
                        'account_id' => $accountId, 'debit' => (int) ($b['debit'] ?? 0), 'credit' => (int) ($b['credit'] ?? 0),
                    ], array_keys($balances), $balances),
                    'created_by' => $actor?->id,
                ]), $actor);
            }

            foreach ($invoices as $open) {
                $result['invoices'][] = $this->postInvoice->handle(InvoiceData::from([
                    'company_id' => $company->id,
                    'customer_id' => (int) $open['customer_id'],
                    'invoice_date' => $open['date'],
                    'due_date' => $open['due_date'] ?: null,
                    'is_opening' => true,
                    'reference_no' => $open['number'] ?: null,
                    'memo' => 'Opening balance — '.($open['number'] ?: 'open invoice'),
                    'lines' => [['description' => 'Opening balance'.($open['number'] ? " — {$open['number']}" : ''), 'qty' => '1', 'unit_price' => (int) $open['amount'],
                        'tax_code_id' => $exempt->id, 'income_account_id' => $obe->id]],
                ]), $actor);
            }

            foreach ($bills as $open) {
                $result['bills'][] = $this->postBill->handle(BillData::from([
                    'company_id' => $company->id,
                    'vendor_id' => (int) $open['vendor_id'],
                    'bill_date' => $open['date'],
                    'due_date' => $open['due_date'] ?: null,
                    'is_opening' => true,
                    'external_reference_no' => $open['number'] ?: null,
                    'memo' => 'Opening balance — '.($open['number'] ?: 'open bill'),
                    'lines' => [['description' => 'Opening balance'.($open['number'] ? " — {$open['number']}" : ''), 'qty' => '1', 'unit_price' => (int) $open['amount'],
                        'tax_code_id' => $exempt->id, 'expense_or_asset_account_id' => $obe->id]],
                ]), $actor);
            }

            foreach ($stock as $count) {
                /** @var Item $item */
                $item = Item::query()->withoutGlobalScopes()->where('company_id', $company->id)->findOrFail((int) $count['item_id']);
                $result['stock'][] = $this->adjust->handle($item, $openingDate, (string) $count['qty'], $obe->id, (int) $count['unit_cost'], 'Opening stock count', $actor);
            }

            return $result;
        });
    }

    /**
     * The cutover is usually dated the day before the first trading period,
     * i.e. in a fiscal year nobody has opened yet: open it, so the entry
     * has a period to land in.
     */
    private function ensurePeriodFor(Company $company, string $date): void
    {
        $exists = AccountingPeriod::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->containing($date)->exists();
        if ($exists) {
            return;
        }
        $day = Carbon::parse($date);
        $fiscalYear = $day->month >= $company->fiscal_year_start_month ? $day->year : $day->year - 1;
        $this->openYear->handle($company, $fiscalYear);
    }

    /**
     * The balance-sheet accounts a balance may be typed for: everything but
     * the receivable and payable controls, Opening Balance Equity itself and
     * the inventory accounts that stocked items post to.
     *
     * @return list<Account>
     */
    public function enterableAccounts(Company $company): array
    {
        $excluded = $this->fedElsewhere($company);

        return Account::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->where('is_active', true)
            ->whereIn('type', [AccountType::Asset, AccountType::Liability, AccountType::Equity])
            ->whereNotIn('id', array_keys($excluded))
            ->orderBy('code')->get()->all();
    }

    /**
     * Accounts whose opening figure comes from another step: id => why.
     *
     * @return array<int, string>
     */
    public function fedElsewhere(Company $company): array
    {
        $reasons = [
            $this->account($company, '1200')->id => 'receivables come from the customers\' open invoices',
            $this->account($company, '2100')->id => 'payables come from the vendors\' open bills',
            $this->account($company, '3950')->id => 'Opening Balance Equity is the offset and takes the difference',
        ];
        $stockAccounts = Item::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->where('type', ItemType::Inventory)
            ->whereNotNull('inventory_account_id')->distinct()->pluck('inventory_account_id');
        foreach ($stockAccounts as $accountId) {
            $reasons[(int) $accountId] = 'inventory comes from the stock on hand';
        }

        return $reasons;
    }

    /**
     * @param  list<int>  $accountIds
     */
    private function assertBalancesAreEnterable(Company $company, array $accountIds): void
    {
        $reasons = $this->fedElsewhere($company);
        foreach ($accountIds as $accountId) {
            if (isset($reasons[$accountId])) {
                $account = Account::query()->withoutGlobalScopes()->find($accountId);
                throw new RuntimeException(sprintf('%s %s: %s.', $account?->code, $account?->name, $reasons[$accountId]));
            }
        }
    }

    private function account(Company $company, string $code): Account
    {
        return Account::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->where('code', $code)->firstOrFail();
    }
}
