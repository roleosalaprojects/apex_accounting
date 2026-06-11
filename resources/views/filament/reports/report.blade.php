<x-filament-panels::page>
    <form wire:submit.prevent class="flex flex-wrap items-end gap-3">
        @if ($usesRange)
            <div>
                <label class="text-sm font-medium">From</label>
                <input type="date" wire:model.live="from" class="block rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600" />
            </div>
        @endif
        <div>
            <label class="text-sm font-medium">{{ $usesRange ? 'To' : 'As of' }}</label>
            <input type="date" wire:model.live="asOf" class="block rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600" />
        </div>
    </form>

    @if (! empty($report['meta']['label']))
        <p class="text-sm {{ ($report['meta']['ok'] ?? true) ? 'text-success-600' : 'text-danger-600' }}">
            {{ $report['meta']['label'] }}
        </p>
    @endif

    <div class="fi-ta rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    @foreach ($report['columns'] as $i => $col)
                        <th class="px-3 py-2 text-left font-semibold {{ $i > 0 ? 'text-right' : '' }}">{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse ($report['rows'] as $row)
                    <tr>
                        @foreach ($row as $i => $cell)
                            <td class="px-3 py-1.5 {{ $i > 0 ? 'text-right tabular-nums' : '' }}">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td class="px-3 py-3 text-gray-500" colspan="{{ count($report['columns']) }}">No data for this period.</td></tr>
                @endforelse
            </tbody>
            @if (! empty($report['totals']))
                <tfoot class="bg-gray-50 dark:bg-gray-800 font-semibold">
                    <tr>
                        @foreach ($report['totals'] as $i => $cell)
                            <td class="px-3 py-2 {{ $i > 0 ? 'text-right tabular-nums' : '' }}">{{ $cell }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</x-filament-panels::page>
