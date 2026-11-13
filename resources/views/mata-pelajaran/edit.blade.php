
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Edit Mata Pelajaran
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <h3 class="text-lg font-bold mb-6">
                    Form Edit Mata Pelajaran
                </h3>

                @if ($errors->any())
                    <div class="mb-5 p-4 bg-red-100 text-red-700 rounded">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('mata-pelajaran.update', $mataPelajaran->id) }}"
                      method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="block font-medium mb-2">
                            Kode Mata Pelajaran
                        </label>

                        <input type="text"
                               name="kode_mapel"
                               value="{{ old('kode_mapel', $mataPelajaran->kode_mapel) }}"
                               class="w-full border-gray-300 rounded-lg"
                               required>
                    </div>

                    <div class="mb-4">
                        <label class="block font-medium mb-2">
                            Nama Mata Pelajaran
                        </label>

                        <input type="text"
                               name="nama_mapel"
                               value="{{ old('nama_mapel', $mataPelajaran->nama_mapel) }}"
                               class="w-full border-gray-300 rounded-lg"
                               required>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div class="mb-4">
                            <label class="block font-medium mb-2">
                                Jumlah SKS
                            </label>

                            <input type="number"
                                   name="sks"
                                   value="{{ old('sks', $mataPelajaran->sks) }}"
                                   min="1"
                                   class="w-full border-gray-300 rounded-lg"
                                   required>
                        </div>

                        <div class="mb-4">
                            <label class="block font-medium mb-2">
                                Semester
                            </label>

                            <input type="number"
                                   name="semester"
                                   value="{{ old('semester', $mataPelajaran->semester) }}"
                                   min="1"
                                   class="w-full border-gray-300 rounded-lg"
                                   required>
                        </div>

                    </div>

                    <div class="mb-6">
                        <label class="block font-medium mb-2">
                            Status
                        </label>

                        <select name="status"
                                class="w-full border-gray-300 rounded-lg"
                                required>
                            <option value="1" {{ old('status', $mataPelajaran->status) == 1 ? 'selected' : '' }}>
                                Aktif
                            </option>

                            <option value="0" {{ old('status', $mataPelajaran->status) == 0 ? 'selected' : '' }}>
                                Tidak Aktif
                            </option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('mata-pelajaran.index') }}"
                           class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg">
                            Kembali
                        </a>

                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                            Perbarui Data
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>
</x-app-layout>

