<x-filament-widgets::widget>
    <x-filament::section heading="BIR filing calendar" description="Next deadline for each return, with what the ledger shows today.">
        <ol class="-my-3 divide-y divide-gray-950/5 dark:divide-white/10">
            @foreach ($deadlines as $deadline)
                <li class="py-3">
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $deadline['title'] }}</p>
                        <p class="shrink-0 text-sm font-medium tabular-nums text-gray-950 dark:text-white">{{ $deadline['due'] }}</p>
                    </div>

                    <div class="mt-1 flex items-center justify-between gap-3 text-xs">
                        <p class="flex min-w-0 items-center gap-2 text-gray-500 dark:text-gray-400">
                            <span class="whitespace-nowrap rounded bg-gray-950/5 px-1.5 py-0.5 font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $deadline['form'] }}</span>
                            <span class="truncate">{{ $deadline['period'] }}</span>
                        </p>

                        @if ($deadline['soon'])
                            <p class="flex shrink-0 items-center gap-1 font-medium text-warning-700 dark:text-warning-400">
                                <x-filament::icon icon="heroicon-m-clock" class="size-3.5" />
                                {{ $deadline['when'] }}
                            </p>
                        @else
                            <p class="shrink-0 text-gray-500 dark:text-gray-400">{{ $deadline['when'] }}</p>
                        @endif
                    </div>

                    @if ($deadline['amount'])
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $deadline['amountLabel'] }}
                            <span class="font-medium tabular-nums text-gray-700 dark:text-gray-200">{{ $deadline['amount'] }}</span>
                        </p>
                    @endif
                </li>
            @endforeach
        </ol>

        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            Manual-filing deadlines; eFPS filers of monthly withholding returns follow the staggered 11th–15th schedule.
            Income tax dates assume a corporation.
            @if ($returnsUrl)
                <a href="{{ $returnsUrl }}" class="fi-link font-medium">Tax returns →</a>
            @endif
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
