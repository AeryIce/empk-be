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
            Halaman ini <b>DEMO</b> untuk presentasi. Klik "Simpan (Demo)" hanya menampilkan notifikasi dan tidak menyimpan data.
        </x-slot>
        <div class="prose text-sm">
            <ul class="list-disc pl-5">
                <li>Setelah presentasi disetujui, tombol ini akan dihubungkan ke CRUD nyata.</li>
                <li>Struktur field bisa kita sesuaikan sesuai kebutuhan final.</li>
            </ul>
        </div>
    </x-filament::section>
</x-filament::page>
