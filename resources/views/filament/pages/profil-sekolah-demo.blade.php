<x-filament::page>
    <form wire:submit.prevent="submit" class="space-y-6">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Simpan (Demo)
            </x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Catatan</x-slot>
        <x-slot name="description">
            Halaman ini <b>DEMO</b> untuk presentasi esok. Setelah disetujui, form ini akan dihubungkan ke database dan
            halaman publik e-MPK akan menampilkan profil sekolah secara real-time.
        </x-slot>
    </x-filament::section>
</x-filament::page>
