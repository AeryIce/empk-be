<x-filament::widget>
    <x-filament::card>
        <div class="grid gap-3 sm:grid-cols-3">
            <x-filament::button tag="a" size="xl" href="{{ url('/admin/post-info-demo') }}" icon="heroicon-o-newspaper">
                Post Info (Demo)
            </x-filament::button>

            <x-filament::button tag="a" size="xl" href="{{ url('/admin/daftar-info-demo') }}" icon="heroicon-o-list-bullet" color="gray">
                Daftar Info (Demo)
            </x-filament::button>

            <x-filament::button tag="a" size="xl" href="{{ url('/admin/profil-sekolah-demo') }}" icon="heroicon-o-building-office" color="gray">
                Profil Sekolah (Demo)
            </x-filament::button>
        </div>
    </x-filament::card>
</x-filament::widget>
