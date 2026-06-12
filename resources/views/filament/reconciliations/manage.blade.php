<x-filament-panels::page>
    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-sm text-gray-500">Statement ending balance</div>
            <div class="text-xl font-semibold tabular-nums">₱{{ number_format($reconciliation->statement_ending_balance->minor / 100, 2) }}</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-sm text-gray-500">Cleared balance</div>
            <div class="text-xl font-semibold tabular-nums">₱{{ number_format($clearedBalance / 100, 2) }}</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-sm text-gray-500">Difference</div>
            <div class="text-xl font-semibold tabular-nums {{ $difference === 0 ? 'text-success-600' : 'text-danger-600' }}">
                ₱{{ number_format($difference / 100, 2) }}
            </div>
        </div>
    </div>

    @if ($reconciliation->status === 'completed')
        <p class="text-sm text-success-600 font-medium">This reconciliation is completed and locked.</p>
    @else
        <p class="text-sm text-gray-500">Untick anything that has not yet cleared the bank. Complete when the difference is zero.</p>
    @endif

    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold">Cleared</th>
                    <th class="px-3 py-2 text-left font-semibold">Date</th>
                    <th class="px-3 py-2 text-left font-semibold">JE No.</th>
                    <th class="px-3 py-2 text-left font-semibold">Memo</th>
                    <th class="px-3 py-2 text-right font-semibold">Debit</th>
                    <th class="px-3 py-2 text-right font-semibold">Credit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse ($reconciliation->items as $item)
                    <tr>
                        <td class="px-3 py-1.5">
                            <input type="checkbox"
                                   @checked($item->is_cleared)
                                   @disabled($reconciliation->status === 'completed')
                                   wire:click="toggle({{ $item->id }})"
                                   class="rounded border-gray-300 dark:bg-gray-800 dark:border-gray-600" />
                        </td>
                        <td class="px-3 py-1.5">{{ $item->journalLine?->journalEntry?->entry_date?->toDateString() }}</td>
                        <td class="px-3 py-1.5">{{ $item->journalLine?->journalEntry?->number }}</td>
                        <td class="px-3 py-1.5">{{ $item->journalLine?->memo ?? $item->journalLine?->journalEntry?->memo }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format(($item->journalLine?->debit->minor ?? 0) / 100, 2) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format(($item->journalLine?->credit->minor ?? 0) / 100, 2) }}</td>
                    </tr>
                @empty
                    <tr><td class="px-3 py-3 text-gray-500" colspan="6">No bank lines up to the statement date.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
