<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detail Mata Pelajaran</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><dt class="font-semibold">Kode</dt><dd>{{ $mataPelajaran->kode_mapel }}</dd></div>
                    <div><dt class="font-semibold">Nama</dt><dd>{{ $mataPelajaran->nama_mapel }}</dd></div>
                    <div><dt class="font-semibold">SKS</dt><dd>{{ $mataPelajaran->sks }}</dd></div>
                    <div><dt class="font-semibold">Semester</dt><dd>{{ $mataPelajaran->semester }}</dd></div>
                    <div><dt class="font-semibold">Status</dt><dd>{{ $mataPelajaran->status ? 'Aktif' : 'Tidak Aktif' }}</dd></div>
                </dl>
                <div class="mt-6 flex gap-3">
                    <a href="{{ route('mata-pelajaran.edit', $mataPelajaran) }}" class="bg-blue-600 text-white px-4 py-2 rounded">Edit</a>
                    <a href="{{ route('mata-pelajaran.index') }}" class="border px-4 py-2 rounded">Kembali</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
