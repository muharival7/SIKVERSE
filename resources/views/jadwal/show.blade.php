<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detail Jadwal</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><dt class="font-semibold">Mata Pelajaran</dt><dd>{{ $jadwal->mataPelajaran?->nama_mapel ?? '—' }}</dd></div>
                    <div><dt class="font-semibold">Instruktur</dt><dd>{{ $jadwal->infrastrukturSik?->nama ?? '—' }}</dd></div>
                    <div><dt class="font-semibold">Hari</dt><dd>{{ $jadwal->hari }}</dd></div>
                    <div><dt class="font-semibold">Waktu</dt><dd>{{ substr($jadwal->waktu_mulai, 0, 5) }}–{{ substr($jadwal->waktu_selesai, 0, 5) }}</dd></div>
                    <div><dt class="font-semibold">Ruang</dt><dd>{{ $jadwal->ruang }}</dd></div>
                    <div><dt class="font-semibold">Status</dt><dd>{{ $jadwal->status ? 'Aktif' : 'Tidak Aktif' }}</dd></div>
                </dl>
                <div class="mt-6 flex gap-3">
                    <a href="{{ route('jadwal.edit', $jadwal) }}" class="bg-blue-600 text-white px-4 py-2 rounded">Edit</a>
                    <a href="{{ route('jadwal.index') }}" class="border px-4 py-2 rounded">Kembali</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
