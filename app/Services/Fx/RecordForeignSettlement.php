<?php

declare(strict_types=1);

namespace App\Services\Fx;

use App\Actions\Ledger\PostJournalEntry;
use App\Actions\Receivables\ReceiveCustomerPayment;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Data\Receivables\CustomerPaymentData;
use App\Data\Receivables\PaymentApplicationData;
use App\Enums\AccountSubtype;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * Realized FX on settlement of a foreign-currency invoice (§17). AR was booked
 * in functional currency (PHP) at the issue rate; the customer settles the same
 * foreign amount at a (possibly) different rate. We clear AR at the booked PHP
 * amount through the normal payment path, then post the rate difference to the
 * Foreign Exchange Gain (Loss) account so cash ends at the actual amount.
 */
final class RecordForeignSettlement
{
    private const FX_ACCOUNT_CODE = '4950';

    public function __construct(
        private readonly ReceiveCustomerPayment $receivePayment,
        private readonly PostJournalEntry $post,
    ) {}

    /**
     * @return array{cash: int, fx: int, booked: int, fx_entry: ?JournalEntry}
     */
    public function handle(Invoice $invoice, float $settlementRate, int $depositAccountId, string $settlementDate, ?User $actor = null): array
    {
        if (! $invoice->isForeignCurrency()) {
            throw new RuntimeException('Invoice is not in a foreign currency.');
        }

        $booked = $invoice->outstanding();
        if ($booked <= 0) {
            throw new RuntimeException('Invoice has no outstanding balance to settle.');
        }

        $issueRate = (float) $invoice->exchange_rate;
        if ($issueRate <= 0.0) {
            throw new RuntimeException('Invoice is missing its issue exchange rate.');
        }

        // Cash actually received in functional currency = foreign amount × settlement rate.
        $cash = (int) round($booked / $issueRate * $settlementRate);
        $fx = $cash - $booked;

        return DB::transaction(function () use ($invoice, $booked, $cash, $fx, $depositAccountId, $settlementDate, $actor): array {
            // 1. Clear AR at the booked functional amount via the normal payment path.
            $this->receivePayment->handle(new CustomerPaymentData(
                company_id: $invoice->company_id,
                customer_id: $invoice->customer_id,
                payment_date: $settlementDate,
                deposit_to_account_id: $depositAccountId,
                amount: $booked,
                applications: new DataCollection(PaymentApplicationData::class, [
                    new PaymentApplicationData(invoice_id: $invoice->id, amount: $booked),
                ]),
                created_by: $actor?->id,
            ), $actor);

            // 2. Post the rate difference so cash ends at the actual amount received.
            $fxEntry = null;
            if ($fx !== 0) {
                $fxAccountId = $this->fxAccount($invoice->company_id)->id;
                $lines = $fx > 0
                    ? [
                        new JournalLineData(account_id: $depositAccountId, debit: $fx, memo: 'FX gain on settlement'),
                        new JournalLineData(account_id: $fxAccountId, credit: $fx, memo: 'FX gain on settlement'),
                    ]
                    : [
                        new JournalLineData(account_id: $fxAccountId, debit: -$fx, memo: 'FX loss on settlement'),
                        new JournalLineData(account_id: $depositAccountId, credit: -$fx, memo: 'FX loss on settlement'),
                    ];

                $fxEntry = $this->post->handle(new JournalEntryData(
                    company_id: $invoice->company_id,
                    entry_date: $settlementDate,
                    memo: 'FX settlement adjustment — invoice '.(string) $invoice->number,
                    lines: new DataCollection(JournalLineData::class, $lines),
                    created_by: $actor?->id,
                    approved_by: $actor?->id,
                ), $actor);
            }

            return ['cash' => $cash, 'fx' => $fx, 'booked' => $booked, 'fx_entry' => $fxEntry];
        });
    }

    private function fxAccount(int $companyId): Account
    {
        $existing = Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->where('code', self::FX_ACCOUNT_CODE)->first();

        if ($existing !== null) {
            return $existing;
        }

        $subtype = AccountSubtype::OtherIncome;
        $account = new Account;
        $account->forceFill([
            'company_id' => $companyId,
            'code' => self::FX_ACCOUNT_CODE,
            'name' => 'Foreign Exchange Gain (Loss)',
            'type' => $subtype->type(),
            'subtype' => $subtype,
            'normal_balance' => $subtype->normalBalance(),
            'is_system' => true,
            'is_active' => true,
        ]);
        $account->save();

        return $account;
    }
}
