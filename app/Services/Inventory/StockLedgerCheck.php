<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\ItemType;
use App\Enums\JournalStatus;
use Illuminate\Support\Facades\DB;

/**
 * The stock subledger against the ledger (§9): for every account that holds a
 * stocked item, the value of the stock on hand must equal the account's
 * balance. A difference means stock moved without a matching posting (or the
 * other way round), e.g. an item's cost debited to the wrong account.
 */
final class StockLedgerCheck
{
    /**
     * @return list<array{account_id: int, code: string, name: string, ledger: int, stock: int}>
     */
    public function differences(int $companyId): array
    {
        $stock = DB::table('item_valuations')
            ->join('items', 'items.id', '=', 'item_valuations.item_id')
            ->where('item_valuations.company_id', $companyId)
            ->where('items.type', ItemType::Inventory->value)
            ->whereNotNull('items.inventory_account_id')
            ->groupBy('items.inventory_account_id')
            ->selectRaw('items.inventory_account_id as account_id, SUM(item_valuations.value) as stock')
            ->pluck('stock', 'account_id');

        $accountIds = DB::table('items')
            ->where('company_id', $companyId)
            ->where('type', ItemType::Inventory->value)
            ->whereNotNull('inventory_account_id')
            ->distinct()
            ->pluck('inventory_account_id');

        $differences = [];
        foreach ($accountIds as $accountId) {
            $ledger = (int) DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_entries.company_id', $companyId)
                ->whereIn('journal_entries.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
                ->where('journal_lines.account_id', $accountId)
                ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) - COALESCE(SUM(journal_lines.credit), 0) as balance')
                ->value('balance');
            $onHand = (int) ($stock[$accountId] ?? 0);

            if ($ledger !== $onHand) {
                $account = DB::table('accounts')->where('id', $accountId)->first(['code', 'name']);
                $differences[] = [
                    'account_id' => (int) $accountId,
                    'code' => (string) ($account->code ?? '?'),
                    'name' => (string) ($account->name ?? ''),
                    'ledger' => $ledger,
                    'stock' => $onHand,
                ];
            }
        }

        return $differences;
    }
}
