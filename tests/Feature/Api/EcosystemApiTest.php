<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Models\JournalEntry;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->company = makeCompany();
    $this->client = makeUserWithRole($this->company, CompanyRole::Accountant);
});

/**
 * A balanced day: tenders 160k + discount 2k (debits) = sales 150k + VAT 12k (credits).
 *
 * @return array<string, mixed>
 */
function posZreadingPayload(int $companyId): array
{
    return [
        'company_id' => $companyId,
        'business_date' => '2026-06-15',
        'reference' => 'Z-0001',
        'vatable_sales' => 100_000_00,
        'exempt_sales' => 50_000_00,
        'zero_rated_sales' => 0,
        'vat_amount' => 12_000_00,
        'discounts' => 2_000_00,
        'tenders' => ['cash' => 100_000_00, 'card' => 60_000_00],
    ];
}

it('posts a POS Z-reading as a balanced journal entry (pos:post)', function () {
    Passport::actingAs($this->client, ['pos:post']);

    $response = $this->postJson('/api/v1/pos/z-readings', posZreadingPayload($this->company->id));

    $response->assertCreated()
        ->assertJsonPath('status', 'posted')
        ->assertJsonPath('total_debits', 162_000_00);

    expect(JournalEntry::query()->count())->toBe(1);

    $entry = JournalEntry::query()->with('lines')->first();
    expect($entry->lines->firstWhere('account_id', account($this->company, '2200')->id)->credit->minor)->toBe(12_000_00)
        ->and($entry->lines->firstWhere('account_id', account($this->company, '1110')->id)->debit->minor)->toBe(100_000_00);
});

it('rejects an inconsistent (unbalanced) Z-reading', function () {
    Passport::actingAs($this->client, ['pos:post']);

    $payload = posZreadingPayload($this->company->id);
    $payload['tenders'] = ['cash' => 1_000_00]; // does not match the sales total

    $this->postJson('/api/v1/pos/z-readings', $payload)->assertStatus(422);
    expect(JournalEntry::query()->count())->toBe(0);
});

it('rejects a POS Z-reading without the pos:post scope', function () {
    Passport::actingAs($this->client, ['reports:read']);

    $this->postJson('/api/v1/pos/z-readings', posZreadingPayload($this->company->id))->assertForbidden();
});

it('replays a POS Z-reading idempotently', function () {
    Passport::actingAs($this->client, ['pos:post']);
    $headers = ['Idempotency-Key' => 'pos-z-2026-06-15'];

    $first = $this->withHeaders($headers)->postJson('/api/v1/pos/z-readings', posZreadingPayload($this->company->id));
    $second = $this->withHeaders($headers)->postJson('/api/v1/pos/z-readings', posZreadingPayload($this->company->id));

    $first->assertCreated();
    $second->assertCreated()->assertHeader('Idempotent-Replay', 'true');

    expect($first->json('id'))->toBe($second->json('id'))
        ->and(JournalEntry::query()->count())->toBe(1);
});

it('posts an HRMS payroll summary as a balanced journal entry (hrms:post)', function () {
    Passport::actingAs($this->client, ['hrms:post']);

    $response = $this->postJson('/api/v1/hrms/payroll', [
        'company_id' => $this->company->id,
        'pay_date' => '2026-06-15',
        'reference' => 'PR-2026-06-A',
        'gross_pay' => 100_000_00,
        'employer_contributions' => 8_000_00,
        'withholding_tax' => 5_000_00,
        'statutory_employee' => 3_000_00,
        'net_pay' => 92_000_00,
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'posted')
        ->assertJsonPath('total_debits', 108_000_00);

    $entry = JournalEntry::query()->with('lines')->first();
    expect($entry->lines->firstWhere('account_id', account($this->company, '6300')->id)->debit->minor)->toBe(100_000_00)
        ->and($entry->lines->firstWhere('account_id', account($this->company, '2230')->id)->credit->minor)->toBe(11_000_00);
});

it('rejects HRMS payroll without the hrms:post scope', function () {
    Passport::actingAs($this->client, ['pos:post']);

    $this->postJson('/api/v1/hrms/payroll', [
        'company_id' => $this->company->id,
        'pay_date' => '2026-06-15',
        'gross_pay' => 100_000_00,
        'net_pay' => 100_000_00,
    ])->assertForbidden();
});
