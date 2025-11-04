<x-filament::widget>
    <div class="overflow-hidden rounded-2xl shadow-sm ring-1 ring-black/5">
        {{-- Banner gradient --}}
        <div class="bg-gradient-to-r from-emerald-600 via-sky-600 to-indigo-600 text-white">
            <div class="px-6 py-6 sm:px-8 flex items-start justify-between">
                <div>
                    <div class="text-sm/6 opacity-90">{{ $today }}</div>
                    <div class="mt-1 text-2xl font-bold tracking-tight">
                        Selamat datang, {{ $name }} ✨
                    </div>
                    <div class="mt-1 text-sm font-medium">
                        Menuju rapat 7 Nov: <span class="font-semibold">{{ $countdown }}</span>
                    </div>
                </div>
                <svg class="hidden sm:block w-16 h-16 opacity-80" viewBox="0 0 24 24" fill="none">
                    <path d="M5 13l4 4L19 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
        </div>

        {{-- Foot line (optional hint) --}}
        <div class="bg-white dark:bg-gray-900 px-6 py-3 sm:px-8 text-xs text-gray-600 dark:text-gray-300">
            Gunakan menu <b>Demo</b> di sidebar untuk mencoba alur input & daftar info.
        </div>
    </div>
</x-filament::widget>
