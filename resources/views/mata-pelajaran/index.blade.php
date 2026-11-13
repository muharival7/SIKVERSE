
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Data Mata Pelajaran
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
                    <h3 class="text-lg font-bold">
                        Daftar Mata Pelajaran
                    </h3>

                    <a href="{{ route('mata-pelajaran.create') }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        + Tambah Mata Pelajaran
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="border p-3">No</th>
                                <th class="border p-3">Kode</th>
                                <th class="border p-3">Nama Mata Pelajaran</th>
                                <th class="border p-3">SKS</th>
                                <th class="border p-3">Semester</th>
                                <th class="border p-3">Status</th>
                                <th class="border p-3">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($mataPelajaran as $index => $mapel)
                                <tr>
                                    <td class="border p-3 text-center">
                                        {{ $mataPelajaran->firstItem() + $index }}
                                    </td>

                                    <td class="border p-3">
                                        {{ $mapel->kode_mapel }}
                                    </td>

                                    <td class="border p-3">
                                        {{ $mapel->nama_mapel }}
                                    </td>

                                    <td class="border p-3 text-center">
                                        {{ $mapel->sks }}
                                    </td>

                                    <td class="border p-3 text-center">
                                        {{ $mapel->semester }}
                                    </td>

                                    <td class="border p-3 text-center">
                                        {{ $mapel->status ? 'Aktif' : 'Tidak Aktif' }}
                                    </td>

                                    <td class="border p-3 text-center">
                                        <a href="{{ route('mata-pelajaran.edit', $mapel->id) }}"
                                           class="text-blue-600 hover:underline">
                                            Edit
                                        </a>

                                        <form action="{{ route('mata-pelajaran.destroy', $mapel->id) }}"
                                              method="POST"
                                              class="inline"
                                              onsubmit="return confirm('Yakin ingin menghapus mata pelajaran ini?')">
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
                                    <td colspan="7" class="border p-4 text-center text-gray-500">
                                        Belum ada data mata pelajaran.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-5">
                    {{ $mataPelajaran->links() }}
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
