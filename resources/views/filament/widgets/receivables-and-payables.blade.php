<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid gap-8 md:grid-cols-2">
            @foreach ([$receivables, $payables] as $side)
                <div class="flex min-w-0 flex-col">
                    <div class="flex items-baseline justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $side['title'] }}</h3>
                        <p class="text-xl font-semibold tracking-tight text-gray-950 dark:text-white" title="{{ $side['exact'] }}">{{ $side['total'] }}</p>
                    </div>

                    <dl class="mt-4 space-y-3">
                        @foreach ($side['rows'] as $row)
                            <div>
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <dt class="text-gray-600 dark:text-gray-300">{{ $row['label'] }}</dt>
                                    <dd class="font-medium tabular-nums text-gray-950 dark:text-white" title="{{ $row['exact'] }}">{{ $row['amount'] }}</dd>
                                </div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full" style="background: var(--viz-track)" role="presentation">
                                    <div class="h-full rounded-full" style="width: {{ $row['share'] }}%; background: var(--viz-series-1)"></div>
                                </div>
                            </div>
                        @endforeach
                    </dl>

                    <div class="mt-6 border-t border-gray-950/5 pt-4 dark:border-white/10">
                        <h4 class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $side['listTitle'] }}</h4>

                        @forelse ($side['list'] as $item)
                            <div class="mt-2 flex items-baseline justify-between gap-3 text-sm">
                                <p class="min-w-0 truncate text-gray-950 dark:text-white">
                                    {{ $item['name'] }}
                                    @if ($item['detail'])
                                        <span class="text-xs text-gray-500 dark:text-gray-400">· {{ $item['detail'] }}</span>
                                    @endif
                                </p>
                                <p class="shrink-0 font-medium tabular-nums text-gray-950 dark:text-white">{{ $item['amount'] }}</p>
                            </div>
                        @empty
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $side['empty'] }}</p>
                        @endforelse
                    </div>

                    <a href="{{ $side['link'] }}" class="fi-link mt-auto self-start pt-4 text-sm font-medium">{{ $side['linkLabel'] }} →</a>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
