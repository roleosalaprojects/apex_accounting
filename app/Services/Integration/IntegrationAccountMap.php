<?php

declare(strict_types=1);

namespace App\Services\Integration;

use App\Models\Account;
use RuntimeException;

/**
 * Resolves the GL accounts an ecosystem integration posts to, keyed by semantic
 * name, so client apps (POS, HRMS) never embed chart-of-account codes. Defaults
 * map to the standard chart seeded by SetupNewCompany. (§14)
 */
final class IntegrationAccountMap
{
    /** @var array<string, string> */
    private const POS = [
        'cash' => '1110',
        'card' => '1120',
        'ewallet' => '1120',
        'cheque' => '1120',
        'bank_transfer' => '1120',
        'gift_cert' => '1110',
        'sales_vatable' => '4200',
        'sales_exempt' => '4100',
        'sales_zero_rated' => '4100',
        'output_vat' => '2200',
        'discount' => '4200',
    ];

    /** @var array<string, string> */
    private const HRMS = [
        'salaries' => '6300',
        'employer_contributions' => '6310',
        'withholding_tax' => '2220',
        'statutory_payable' => '2230',
        'net_pay' => '1120',
    ];

    /**
     * @return array<string, int>
     */
    public function pos(int $companyId): array
    {
        return $this->resolve($companyId, self::POS);
    }

    /**
     * @return array<string, int>
     */
    public function hrms(int $companyId): array
    {
        return $this->resolve($companyId, self::HRMS);
    }

    /**
     * @param  array<string, string>  $map
     * @return array<string, int>
     */
    private function resolve(int $companyId, array $map): array
    {
        $ids = Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('code', array_values(array_unique($map)))
            ->pluck('id', 'code');

        $resolved = [];
        foreach ($map as $key => $code) {
            $id = $ids[$code] ?? null;
            if ($id === null) {
                throw new RuntimeException("Chart of accounts is missing integration account {$code}.");
            }
            $resolved[$key] = (int) $id;
        }

        return $resolved;
    }
}
