
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-white leading-tight">
            Tambah Kelompok
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <h3 class="text-lg font-bold text-gray-800 mb-6">
                    Form Tambah Kelompok SIK
                </h3>

                @if ($errors->any())
                    <div class="mb-4 rounded-lg bg-red-100 text-red-800 p-4">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('kelompok.store') }}" method="POST">
                    @csrf

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Periode SIK
                        </label>

                        <select name="periode_sik_id"
                                required
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">-- Pilih Periode --</option>

                            @foreach ($periodes as $periode)
                                <option value="{{ $periode->id }}"
                                    {{ old('periode_sik_id') == $periode->id ? 'selected' : '' }}>
                                    {{ $periode->nama_periode }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Nama Kelompok
                        </label>

                        <input type="text"
                               name="nama_kelompok"
                               value="{{ old('nama_kelompok') }}"
                               placeholder="Contoh: Kelompok A"
                               required
                               maxlength="100"
                               class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Instruktur Tetap
                        </label>

                        <select name="instruktur_id"
                                required
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">-- Pilih Instruktur --</option>

                            @foreach ($instrukturs as $instruktur)
                                <option value="{{ $instruktur->id }}"
                                    {{ old('instruktur_id') == $instruktur->id ? 'selected' : '' }}>
                                    {{ $instruktur->nama }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Keterangan
                        </label>

                        <textarea name="keterangan"
                                  rows="3"
                                  placeholder="Keterangan kelompok (opsional)"
                                  class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">{{ old('keterangan') }}</textarea>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Status
                        </label>

                        <select name="status"
                                required
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="Aktif" {{ old('status', 'Aktif') == 'Aktif' ? 'selected' : '' }}>
                                Aktif
                            </option>
                            <option value="Tidak Aktif" {{ old('status') == 'Tidak Aktif' ? 'selected' : '' }}>
                                Tidak Aktif
                            </option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('kelompok.index') }}"
                           class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300">
                            Kembali
                        </a>

                        <button type="submit"
                                class="px-4 py-2 rounded-lg bg-blue-700 text-white hover:bg-blue-800">
                            Simpan Kelompok
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>

