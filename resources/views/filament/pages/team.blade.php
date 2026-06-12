<x-filament-panels::page>
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold">Name</th>
                    <th class="px-3 py-2 text-left font-semibold">Email</th>
                    <th class="px-3 py-2 text-left font-semibold">Role</th>
                    <th class="px-3 py-2 text-left font-semibold">Member since</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse ($members as $member)
                    <tr>
                        <td class="px-3 py-2 font-medium">{{ $member->name }}</td>
                        <td class="px-3 py-2">{{ $member->email }}</td>
                        <td class="px-3 py-2">
                            <x-filament::badge :color="match ($member->pivot->role) {
                                'owner' => 'danger',
                                'accountant' => 'warning',
                                'bookkeeper' => 'info',
                                default => 'gray',
                            }">
                                {{ ucfirst($member->pivot->role) }}
                            </x-filament::badge>
                        </td>
                        <td class="px-3 py-2 text-gray-500">{{ $member->pivot->created_at?->toDateString() }}</td>
                    </tr>
                @empty
                    <tr><td class="px-3 py-3 text-gray-500" colspan="4">No members.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
