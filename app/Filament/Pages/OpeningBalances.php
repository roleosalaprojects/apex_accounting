<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\AccountType;
use App\Enums\ItemType;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Onboarding\OpeningBalanceSetup;
use App\Support\Money;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Throwable;
use UnitEnum;

/**
 * The guided cutover: the opening trial balance, the customers' open
 * invoices, the vendors' open bills and the stock on hand, reviewed and
 * posted in one go against Opening Balance Equity.
 */
class OpeningBalances extends Page
{
    protected string $view = 'filament.pages.opening-balances';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Opening Balances';

    protected static ?string $title = 'Opening Balances';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows('setupOpeningBalances', Account::class);
    }

    public function mount(): void
    {
        $this->form->fill([
            'opening_date' => Carbon::create(now()->year, $this->company()->fiscal_year_start_month, 1)->subDay()->toDateString(),
            'balances' => [],
            'invoices' => [],
            'bills' => [],
            'stock' => [],
        ]);
    }

    /** The cutover already posted, if any: "Opening balances were posted on …". */
    public function previousCutover(): ?string
    {
        $entry = JournalEntry::query()->where('memo', 'Opening balances (cutover)')->orderByDesc('id')->first();

        return $entry === null ? null : 'Opening balances were posted on '.$entry->entry_date->format('M j, Y')." ({$entry->number}). Posting again adds to them.";
    }

    public function form(Schema $schema): Schema
    {
        $peso = fn (mixed $value): int => filled($value) ? (int) round(((float) str_replace(',', '', (string) $value)) * 100) : 0;

        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    Step::make('Cutover date')
                        ->description('The day the old books close')
                        ->schema([
                            DatePicker::make('opening_date')->label('Opening date')->required()
                                ->helperText('Usually the last day before your first period in this system, e.g. Dec 31. The entry lands in that fiscal year; it is opened for you if it does not exist yet.'),
                        ]),
                    Step::make('Trial balance')
                        ->description('Cash, other assets, liabilities, equity')
                        ->schema(fn (): array => $this->balanceFields())
                        ->afterValidation(fn () => null),
                    Step::make('Open invoices')
                        ->description('What customers still owe')
                        ->schema([
                            Repeater::make('invoices')->label('Customer invoices still unpaid')
                                ->defaultItems(0)->addActionLabel('Add an open invoice')->columns(5)
                                ->schema([
                                    Select::make('customer_id')->label('Customer')->required()->searchable()
                                        ->options(fn (): array => Customer::query()->orderBy('name')->pluck('name', 'id')->all()),
                                    TextInput::make('number')->label('Their invoice no.')->maxLength(60),
                                    DatePicker::make('date')->required(),
                                    DatePicker::make('due_date')->label('Due'),
                                    TextInput::make('amount')->label('Open amount (₱)')->numeric()->required()->minValue(0.01),
                                ]),
                        ]),
                    Step::make('Open bills')
                        ->description('What you still owe vendors')
                        ->schema([
                            Repeater::make('bills')->label('Vendor bills still unpaid')
                                ->defaultItems(0)->addActionLabel('Add an open bill')->columns(5)
                                ->schema([
                                    Select::make('vendor_id')->label('Vendor')->required()->searchable()
                                        ->options(fn (): array => Vendor::query()->orderBy('name')->pluck('name', 'id')->all()),
                                    TextInput::make('number')->label("Vendor's invoice no.")->maxLength(60),
                                    DatePicker::make('date')->required(),
                                    DatePicker::make('due_date')->label('Due'),
                                    TextInput::make('amount')->label('Open amount (₱)')->numeric()->required()->minValue(0.01),
                                ]),
                        ]),
                    Step::make('Stock on hand')
                        ->description('Counted at cost')
                        ->schema([
                            Repeater::make('stock')->label('Stocked items on hand')
                                ->defaultItems(0)->addActionLabel('Add an item')->columns(3)
                                ->schema([
                                    Select::make('item_id')->label('Item')->required()->searchable()
                                        ->options(fn (): array => Item::query()->where('type', ItemType::Inventory)->orderBy('sku')->get()
                                            ->mapWithKeys(fn (Item $item): array => [$item->id => "{$item->sku} — {$item->name}"])->all()),
                                    TextInput::make('qty')->label('Quantity')->numeric()->required()->minValue(0.0001),
                                    TextInput::make('unit_cost')->label('Unit cost (₱)')->numeric()->required()->minValue(0),
                                ]),
                        ]),
                    Step::make('Review')
                        ->description('Check, then post')
                        ->schema([
                            Placeholder::make('summary')->hiddenLabel()
                                ->content(fn (Get $get): HtmlString => $this->summary($get, $peso)),
                        ]),
                ])
                    ->persistStepInQueryString()
                    ->submitAction(new HtmlString(Blade::render('<x-filament::button type="submit" size="lg">Post opening balances</x-filament::button>'))),
            ]);
    }

    /**
     * One debit/credit pair per balance-sheet account a balance may be typed for.
     *
     * @return array<int, Component>
     */
    private function balanceFields(): array
    {
        $setup = app(OpeningBalanceSetup::class);
        $company = $this->company();
        $groups = [];
        foreach ($setup->enterableAccounts($company) as $account) {
            $groups[$account->type->value][] = $account;
        }

        $fieldsets = [
            Placeholder::make('note')->hiddenLabel()->content(
                'Type each account\'s balance from the old books as a debit or a credit. Receivables, payables and stocked inventory are not here — they come from the next steps — and the difference goes to 3950 Opening Balance Equity.'
            ),
        ];
        foreach ([AccountType::Asset, AccountType::Liability, AccountType::Equity] as $type) {
            $rows = [];
            foreach ($groups[$type->value] ?? [] as $account) {
                $rows[] = Placeholder::make("label_{$account->id}")->hiddenLabel()->content("{$account->code} — {$account->name}");
                $rows[] = TextInput::make("balances.{$account->id}.debit")->hiddenLabel()->placeholder('Debit')->numeric()->minValue(0)->live(onBlur: true);
                $rows[] = TextInput::make("balances.{$account->id}.credit")->hiddenLabel()->placeholder('Credit')->numeric()->minValue(0)->live(onBlur: true);
            }
            if ($rows !== []) {
                $fieldsets[] = Fieldset::make(match ($type) {
                    AccountType::Asset => 'Assets', AccountType::Liability => 'Liabilities', default => 'Equity'
                })->schema([Grid::make(3)->schema($rows)]);
            }
        }
        $fieldsets[] = Placeholder::make('tb_totals')->label('Totals')
            ->content(fn (Get $get): string => $this->balanceTotals($get));

        return $fieldsets;
    }

    private function balanceTotals(Get $get): string
    {
        [$debits, $credits] = $this->sumBalances($get('balances') ?? []);
        $diff = $debits - $credits;

        return sprintf('Debits %s · Credits %s · %s', Money::of($debits)->format(), Money::of($credits)->format(),
            $diff === 0 ? 'balanced' : Money::of(abs($diff))->format().' '.($diff > 0 ? 'credited' : 'debited').' to Opening Balance Equity');
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $balances
     * @return array{0: int, 1: int}
     */
    private function sumBalances(array $balances): array
    {
        $debits = 0;
        $credits = 0;
        foreach ($balances as $balance) {
            $debits += filled($balance['debit'] ?? null) ? (int) round(((float) $balance['debit']) * 100) : 0;
            $credits += filled($balance['credit'] ?? null) ? (int) round(((float) $balance['credit']) * 100) : 0;
        }

        return [$debits, $credits];
    }

    private function summary(Get $get, callable $peso): HtmlString
    {
        [$debits, $credits] = $this->sumBalances($get('balances') ?? []);
        $ar = array_sum(array_map(fn (array $i): int => $peso($i['amount'] ?? null), $get('invoices') ?? []));
        $ap = array_sum(array_map(fn (array $b): int => $peso($b['amount'] ?? null), $get('bills') ?? []));
        $stock = array_sum(array_map(fn (array $s): int => (int) round(((float) ($s['qty'] ?? 0)) * $peso($s['unit_cost'] ?? null)), $get('stock') ?? []));
        $equity = $debits - $credits + $ar - $ap + $stock;

        $rows = [
            ['Opening date', Carbon::parse((string) ($get('opening_date') ?: now()))->format('M j, Y')],
            ['Trial balance', Money::of($debits)->format().' debits, '.Money::of($credits)->format().' credits'],
            ['Customer open invoices', count($get('invoices') ?? []).' · '.Money::of($ar)->format()],
            ['Vendor open bills', count($get('bills') ?? []).' · '.Money::of($ap)->format()],
            ['Stock on hand', count($get('stock') ?? []).' items · '.Money::of($stock)->format()],
            ['Opening Balance Equity (3950)', ($equity < 0 ? '−' : '').Money::of(abs($equity))->format().' '.($equity >= 0 ? 'credit' : 'debit').' — the net worth carried in from the old books'],
        ];
        $html = '<dl class="grid grid-cols-1 gap-y-2 text-sm sm:grid-cols-3">';
        foreach ($rows as [$label, $value]) {
            $html .= '<dt class="text-gray-500">'.e($label).'</dt><dd class="font-medium sm:col-span-2">'.e($value).'</dd>';
        }

        return new HtmlString($html.'</dl>');
    }

    public function post(): void
    {
        $data = $this->form->getState();
        $peso = fn (mixed $value): int => filled($value) ? (int) round(((float) str_replace(',', '', (string) $value)) * 100) : 0;
        /** @var User $user */
        $user = Auth::user();

        $balances = [];
        foreach ($data['balances'] ?? [] as $accountId => $balance) {
            $balances[(int) $accountId] = ['debit' => $peso($balance['debit'] ?? null), 'credit' => $peso($balance['credit'] ?? null)];
        }
        $documents = fn (array $rows, string $partyKey): array => array_values(array_map(fn (array $row): array => [
            $partyKey => (int) $row[$partyKey], 'number' => $row['number'] ?? null, 'date' => $row['date'], 'due_date' => $row['due_date'] ?? null, 'amount' => $peso($row['amount']),
        ], $rows));

        try {
            $result = app(OpeningBalanceSetup::class)->handle($this->company(), $data['opening_date'], [
                'balances' => $balances,
                'invoices' => $documents($data['invoices'] ?? [], 'customer_id'),
                'bills' => $documents($data['bills'] ?? [], 'vendor_id'),
                'stock' => array_values(array_map(fn (array $row): array => [
                    'item_id' => (int) $row['item_id'], 'qty' => (string) $row['qty'], 'unit_cost' => $peso($row['unit_cost']),
                ], $data['stock'] ?? [])),
            ], $user);
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Could not post opening balances')->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Opening balances posted')
            ->body(sprintf('%s%d open invoice(s), %d open bill(s) and %d stock line(s) recorded against Opening Balance Equity.',
                $result['entry'] !== null ? "Entry {$result['entry']->number} posted; " : '',
                count($result['invoices']), count($result['bills']), count($result['stock'])))
            ->persistent()->send();
        $this->mount();
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Filament::getTenant();

        return $company;
    }
}
