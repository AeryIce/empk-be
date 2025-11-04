<x-filament::page>
    <div class="flex items-center gap-3">
        <input
            type="text"
            wire:model.debounce.400ms="search"
            placeholder="Cari judul/kategori…"
            class="w-full max-w-md rounded-lg border border-gray-300 p-2"
        />
        <span class="text-sm text-gray-500">Total: {{ count($this->filteredItems) }}</span>
    </div>

    <div class="mt-6 overflow-hidden rounded-xl border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-semibold tracking-wider text-gray-600">Judul</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold tracking-wider text-gray-600">Kategori</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold tracking-wider text-gray-600">Tanggal</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold tracking-wider text-gray-600">Beranda</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($this->filteredItems as $row)
                    <tr>
                        <td class="px-4 py-2 font-medium text-gray-900">
                            {{ $row['judul'] }}
                            <div class="text-xs text-gray-500">{{ $row['ringkasan'] }}</div>
                        </td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-800">
                                {{ ucfirst($row['kategori']) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-gray-700">{{ $row['tanggal_publish'] }}</td>
                        <td class="px-4 py-2">
                            @if($row['tampilkan'])
                                <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">Ya</span>
                            @else
                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">Tidak</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">Tidak ada data</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Catatan</x-slot>
        <x-slot name="description">
            Halaman ini <b>DEMO</b>. Setelah presentasi disetujui, daftar ini akan diambil dari database
            dan tombol "Simpan" pada halaman Post Info akan menambahkan record baru.
        </x-slot>
    </x-filament::section>
</x-filament::page>
