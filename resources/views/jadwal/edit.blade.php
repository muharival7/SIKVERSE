
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Edit Jadwal
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <h3 class="text-lg font-bold mb-6">
                    Form Edit Jadwal
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

                <form action="{{ route('jadwal.update', $jadwal->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="block font-medium mb-2">Mata Pelajaran</label>
                        <select name="mata_pelajaran_id" class="w-full border-gray-300 rounded-lg" required>
                            @foreach ($mataPelajaran as $mapel)
                                <option value="{{ $mapel->id }}"
                                    {{ old('mata_pelajaran_id', $jadwal->mata_pelajaran_id) == $mapel->id ? 'selected' : '' }}>
                                    {{ $mapel->kode_mapel }} - {{ $mapel->nama_mapel }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block font-medium mb-2">Infrastruktur SIK</label>
                        <select name="infrastruktur_sik_id" class="w-full border-gray-300 rounded-lg" required>
                            @foreach ($infrastruktur as $petugas)
                                <option value="{{ $petugas->id }}"
                                    {{ old('infrastruktur_sik_id', $jadwal->infrastruktur_sik_id) == $petugas->id ? 'selected' : '' }}>
                                    {{ $petugas->nama }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block font-medium mb-2">Hari</label>
                        <select name="hari" class="w-full border-gray-300 rounded-lg" required>
                            @foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $hari)
                                <option value="{{ $hari }}"
                                    {{ old('hari', $jadwal->hari) == $hari ? 'selected' : '' }}>
                                    {{ $hari }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="block font-medium mb-2">Waktu Mulai</label>
                            <input type="time"
                                   name="waktu_mulai"
                                   value="{{ old('waktu_mulai', substr($jadwal->waktu_mulai, 0, 5)) }}"
                                   class="w-full border-gray-300 rounded-lg"
                                   required>
                        </div>

                        <div class="mb-4">
                            <label class="block font-medium mb-2">Waktu Selesai</label>
                            <input type="time"
                                   name="waktu_selesai"
                                   value="{{ old('waktu_selesai', substr($jadwal->waktu_selesai, 0, 5)) }}"
                                   class="w-full border-gray-300 rounded-lg"
                                   required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block font-medium mb-2">Ruangan</label>
                        <input type="text"
                               name="ruang"
                               value="{{ old('ruang', $jadwal->ruang) }}"
                               class="w-full border-gray-300 rounded-lg"
                               required>
                    </div>

                    <div class="mb-6">
                        <label class="block font-medium mb-2">Status</label>
                        <select name="status" class="w-full border-gray-300 rounded-lg" required>
                            <option value="1" {{ old('status', (int) $jadwal->status) == 1 ? 'selected' : '' }}>
                                Aktif
                            </option>
                            <option value="0" {{ old('status', (int) $jadwal->status) == 0 ? 'selected' : '' }}>
                                Tidak Aktif
                            </option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('jadwal.index') }}"
                           class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg">
                            Kembali
                        </a>

                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                            Perbarui Jadwal
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
