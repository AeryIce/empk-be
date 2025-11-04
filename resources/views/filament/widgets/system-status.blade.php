<x-filament::widget>
    <x-filament::card>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="font-semibold">{{ $app }}</span>

            @if(!empty($codename))
                <span class="inline-flex items-center rounded-full bg-violet-100 px-2 py-0.5 text-xxs font-semibold text-violet-700">
                    {{ $codename }}
                </span>
            @endif

            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xxs font-semibold
                {{ $env === 'production' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                {{ strtoupper($env) }}
            </span>

            <span class="ml-1">PHP {{ $php }}</span>
            <span>· Laravel {{ $laravel }}</span>
            <span>· {{ $tz }} {{ $now }}</span>

            @if($db_ok)
                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xxs font-semibold text-emerald-700">
                    DB OK
                </span>
            @else
                <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xxs font-semibold text-rose-700">
                    DB FAIL
                </span>
                @if($db_error)
                    <span class="text-xs text-rose-600 truncate max-w-sm" title="{{ $db_error }}">({{ $db_error }})</span>
                @endif
            @endif

            <a class="ml-auto text-xs text-blue-600 hover:underline" href="{{ url('/healthz') }}" target="_blank">/healthz</a>
        </div>
    </x-filament::card>
</x-filament::widget>
