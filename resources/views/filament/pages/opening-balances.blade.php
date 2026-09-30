<x-filament-panels::page>
    @if ($this->previousCutover())
        <div class="rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-300">
            {{ $this->previousCutover() }}
        </div>
    @endif

    <form wire:submit="post">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
