<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Models\JournalEntry;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->company = makeCompany();
    $this->client = makeUserWithRole($this->company, CompanyRole::Accountant);
});

function zReadingPayload(int $companyId): array
{
    return [
        'company_id' => $companyId,
        'entry_date' => '2026-06-15',
        'memo' => 'POS Z-reading',
        'lines' => [
            ['account_id' => account(test()->company, '1110')->id, 'debit' => 1_012_000_00],
            ['account_id' => account(test()->company, '4100')->id, 'credit' => 900_000_00],
            ['account_id' => account(test()->company, '4200')->id, 'credit' => 100_000_00],
            ['account_id' => account(test()->company, '2200')->id, 'credit' => 12_000_00],
        ],
    ];
}

it('posts a POS Z-reading journal entry with the je:post scope', function () {
    Passport::actingAs($this->client, ['je:post']);

    $response = $this->postJson('/api/v1/journal-entries', zReadingPayload($this->company->id));

    $response->assertCreated()
        ->assertJsonPath('status', 'posted')
        ->assertJsonPath('total_debits', 1_012_000_00);

    expect(JournalEntry::query()->count())->toBe(1);
});

it('replays idempotently — same key returns the original result without re-posting', function () {
    Passport::actingAs($this->client, ['je:post']);

    $headers = ['Idempotency-Key' => 'pos-z-2026-06-15'];
    $first = $this->withHeaders($headers)->postJson('/api/v1/journal-entries', zReadingPayload($this->company->id));
    $second = $this->withHeaders($headers)->postJson('/api/v1/journal-entries', zReadingPayload($this->company->id));

    $first->assertCreated();
    $second->assertCreated()->assertHeader('Idempotent-Replay', 'true');

    expect($first->json('id'))->toBe($second->json('id'))
        ->and(JournalEntry::query()->count())->toBe(1); // posted once
});

it('posts the HRMS payroll summary journal entry', function () {
    Passport::actingAs($this->client, ['je:post']);

    $response = $this->postJson('/api/v1/journal-entries', [
        'company_id' => $this->company->id,
        'entry_date' => '2026-06-15',
        'memo' => 'Payroll June 1-15',
        'lines' => [
            ['account_id' => account($this->company, '6300')->id, 'debit' => 100_000_00], // Salaries & Wages
            ['account_id' => account($this->company, '6310')->id, 'debit' => 10_000_00],  // Employer contributions
            ['account_id' => account($this->company, '1120')->id, 'credit' => 95_000_00], // net pay
            ['account_id' => account($this->company, '2210')->id, 'credit' => 5_000_00],  // WTax on compensation (EWT payable stand-in)
            ['account_id' => account($this->company, '2200')->id, 'credit' => 10_000_00], // statutory payables stand-in
        ],
    ]);

    $response->assertCreated()->assertJsonPath('status', 'posted');
});

it('rejects posting without the je:post scope', function () {
    Passport::actingAs($this->client, ['reports:read']);

    $this->postJson('/api/v1/journal-entries', zReadingPayload($this->company->id))
        ->assertForbidden();
});

it('rejects a client that does not belong to the company', function () {
    $outsider = User::factory()->create();
    Passport::actingAs($outsider, ['je:post']);

    $this->postJson('/api/v1/journal-entries', zReadingPayload($this->company->id))
        ->assertForbidden();
});

it('reads accounts and the trial balance with the reports:read scope', function () {
    Passport::actingAs($this->client, ['reports:read']);

    $this->getJson('/api/v1/accounts?company_id='.$this->company->id)
        ->assertOk()
        ->assertJsonFragment(['code' => '1120']);

    $this->getJson('/api/v1/reports/trial-balance?company_id='.$this->company->id.'&as_of=2026-06-30')
        ->assertOk()
        ->assertJsonPath('balanced', true);
});
