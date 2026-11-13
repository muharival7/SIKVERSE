
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Manajemen Jadwal
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                @if (session('success'))
                    <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold">Daftar Jadwal</h3>

                    <a href="{{ route('jadwal.create') }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        + Tambah Jadwal
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="border p-3">No</th>
                                <th class="border p-3">Mata Pelajaran</th>
                                <th class="border p-3">Infrastruktur SIK</th>
                                <th class="border p-3">Hari</th>
                                <th class="border p-3">Waktu</th>
                                <th class="border p-3">Ruang</th>
                                <th class="border p-3">Status</th>
                                <th class="border p-3">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($jadwal as $index => $item)
                                <tr>
                                    <td class="border p-3 text-center">
                                        {{ $jadwal->firstItem() + $index }}
                                    </td>

                                    <td class="border p-3">
                                        {{ $item->mataPelajaran->nama_mapel ?? '-' }}
                                    </td>

                                    <td class="border p-3">
                                        {{ $item->infrastrukturSik->nama ?? '-' }}
                                    </td>

                                    <td class="border p-3 text-center">
                                        {{ $item->hari }}
                                    </td>

                                    <td class="border p-3 text-center">
                                        {{ substr($item->waktu_mulai, 0, 5) }}
                                        -
                                        {{ substr($item->waktu_selesai, 0, 5) }}
                                    </td>

                                    <td class="border p-3 text-center">
                                        {{ $item->ruang }}
                                    </td>

                                    <td class="border p-3 text-center">
                                        {{ $item->status ? 'Aktif' : 'Tidak Aktif' }}
                                    </td>

                                    <td class="border p-3 text-center whitespace-nowrap">
                                        <a href="{{ route('jadwal.edit', $item->id) }}"
                                           class="text-blue-600 hover:underline">
                                            Edit
                                        </a>

                                        <form action="{{ route('jadwal.destroy', $item->id) }}"
                                              method="POST"
                                              class="inline"
                                              onsubmit="return confirm('Yakin ingin menghapus jadwal ini?')">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                    class="text-red-600 hover:underline ml-2">
                                                Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="border p-4 text-center text-gray-500">
                                        Belum ada data jadwal.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-5">
                    {{ $jadwal->links() }}
                </div>

            </div>
        </div>
    </div>
</x-app-layout>

