<x-filament-widgets::widget>
    <x-filament::section heading="Needs attention">
        @if ($items === [])
            <div class="flex items-center gap-3 py-2 text-sm text-gray-600 dark:text-gray-300">
                <x-filament::icon icon="heroicon-o-check-circle" class="size-6 text-success-600 dark:text-success-400" />
                All caught up — nothing is waiting on you.
            </div>
        @else
            <ul class="-my-2 divide-y divide-gray-950/5 dark:divide-white/10">
                @foreach ($items as $item)
                    <li>
                        <a href="{{ $item['url'] }}" class="group -mx-2 flex items-center gap-3 rounded-lg px-2 py-2.5 hover:bg-gray-950/5 dark:hover:bg-white/5">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-gray-950/5 text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                <x-filament::icon :icon="$item['icon']" class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-gray-950 dark:text-white">{{ $item['label'] }}</span>
                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $item['detail'] }}</span>
                            </span>
                            <span class="shrink-0 text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($item['count']) }}</span>
                            <x-filament::icon icon="heroicon-m-chevron-right" class="size-4 shrink-0 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
