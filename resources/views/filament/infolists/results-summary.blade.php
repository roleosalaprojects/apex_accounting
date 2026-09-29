<div class="-my-2 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-gray-500 dark:text-gray-400">
                <th class="py-2 pe-4 text-start font-medium">Period</th>
                <th class="px-4 py-2 text-end font-medium">Income</th>
                <th class="px-4 py-2 text-end font-medium">Expenses</th>
                <th class="py-2 ps-4 text-end font-medium">Net income</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
            @foreach ($getState() as $row)
                <tr>
                    <td class="py-2.5 pe-4">
                        <span class="font-medium text-gray-950 dark:text-white">{{ $row['period'] }}</span>
                        @if ($row['range'])
                            <span class="ms-2 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{{ $row['range'] }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-end tabular-nums text-gray-700 dark:text-gray-200">{{ $row['income'] }}</td>
                    <td class="px-4 py-2.5 text-end tabular-nums text-gray-700 dark:text-gray-200">{{ $row['expenses'] }}</td>
                    <td @class([
                        'py-2.5 ps-4 text-end font-semibold tabular-nums',
                        'text-danger-700 dark:text-danger-400' => $row['loss'],
                        'text-gray-950 dark:text-white' => ! $row['loss'],
                    ])>{{ $row['net'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
