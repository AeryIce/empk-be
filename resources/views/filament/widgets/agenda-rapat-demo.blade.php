<x-filament::widget>
    <x-filament::card>
        <div class="text-base font-semibold mb-3">Agenda Rapat (Demo)</div>

        <div class="space-y-3">
            @foreach($items as $item)
                <div class="flex items-start justify-between rounded-xl border border-gray-200 dark:border-gray-700 p-3">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500"></div>
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item['title'] }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-300">{{ $item['date'] }} · {{ $item['where'] }}</div>
                        </div>
                    </div>
                    <span class="ml-3 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $item['badgeColor'] }}">
                        {{ $item['badge'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </x-filament::card>
</x-filament::widget>
