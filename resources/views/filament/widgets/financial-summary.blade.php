@php
    $tones = [
        'success' => 'text-success-700 dark:text-success-400',
        'danger' => 'text-danger-700 dark:text-danger-400',
        'warning' => 'text-warning-700 dark:text-warning-400',
        'gray' => 'text-gray-500 dark:text-gray-400',
    ];
@endphp

<x-filament-widgets::widget>
    <div class="grid gap-6">
        @foreach ($groups as $group)
            <section>
                <header class="mb-3 flex items-baseline justify-between gap-3">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $group['title'] }}</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $group['caption'] }}</p>
                </header>

                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach ($group['tiles'] as $tile)
                        <article class="flex min-w-0 flex-col rounded-xl bg-white p-4 shadow-xs ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $tile['label'] }}</p>

                            <p class="mt-1 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white" title="{{ $tile['exact'] }}">
                                {{ $tile['value'] }}
                            </p>

                            @if ($tile['note'])
                                <p class="mt-2 flex items-center gap-1 text-xs font-medium {{ $tones[$tile['note']['tone']] }}">
                                    <x-filament::icon :icon="$tile['note']['icon']" class="size-4 shrink-0" />
                                    <span>{{ $tile['note']['text'] }}</span>
                                </p>
                            @endif

                            @if ($tile['sub'])
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $tile['sub'] }}</p>
                            @endif

                            @if ($tile['spark'])
                                {{-- Stretches to the tile's width; the end marker is HTML so it stays round. --}}
                                <div class="mt-auto pt-3" aria-hidden="true">
                                    <div class="relative h-8">
                                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-0 size-full overflow-visible">
                                            <polyline points="{{ $tile['spark']['points'] }}" fill="none" stroke="var(--viz-muted)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                                        </svg>
                                        <span class="absolute size-2 rounded-full" style="left: calc(100% - 4px); top: calc({{ $tile['spark']['lastY'] }}% - 4px); background: var(--viz-series-1); box-shadow: 0 0 0 2px var(--viz-surface)"></span>
                                    </div>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-filament-widgets::widget>
