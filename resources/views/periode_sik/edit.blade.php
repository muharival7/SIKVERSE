
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-gray-800">
            Edit Periode SIK
        </h2>
        <p class="text-sm text-gray-500 mt-1">
            Perbarui informasi periode SIK.
        </p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="bg-white rounded-xl shadow-sm border border-gray-200">

                <div class="p-6 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-800">
                        Formulir Edit Periode
                    </h3>
                </div>

                <form action="{{ route('periode-sik.update', $periodeSik->id) }}"
                      method="POST"
                      class="p-6">

                    @csrf
                    @method('PUT')

                    <div class="space-y-5">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Nama Periode
                            </label>

                            <input type="text"
                                   name="nama_periode"
                                   value="{{ old('nama_periode', $periodeSik->nama_periode) }}"
                                   required
                                   class="w-full rounded-lg border-gray-300 focus:border-[#800020] focus:ring-[#800020]">

                            @error('nama_periode')
                                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Tahun
                            </label>

                            <input type="number"
                                   name="tahun"
                                   min="2000"
                                   max="2100"
                                   value="{{ old('tahun', $periodeSik->tahun) }}"
                                   required
                                   class="w-full rounded-lg border-gray-300 focus:border-[#800020] focus:ring-[#800020]">

                            @error('tahun')
                                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Tanggal Mulai
                                </label>

                                <input type="date"
                                       name="tanggal_mulai"
                                       value="{{ old('tanggal_mulai', $periodeSik->tanggal_mulai->format('Y-m-d')) }}"
                                       required
                                       class="w-full rounded-lg border-gray-300 focus:border-[#800020] focus:ring-[#800020]">

                                @error('tanggal_mulai')
                                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Tanggal Selesai
                                </label>

                                <input type="date"
                                       name="tanggal_selesai"
                                       value="{{ old('tanggal_selesai', $periodeSik->tanggal_selesai->format('Y-m-d')) }}"
                                       required
                                       class="w-full rounded-lg border-gray-300 focus:border-[#800020] focus:ring-[#800020]">

                                @error('tanggal_selesai')
                                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Status Periode
                            </label>

                            <select name="status"
                                    required
                                    class="w-full rounded-lg border-gray-300 focus:border-[#800020] focus:ring-[#800020]">

                                <option value="Persiapan" {{ old('status', $periodeSik->status) === 'Persiapan' ? 'selected' : '' }}>
                                    Persiapan
                                </option>

                                <option value="Aktif" {{ old('status', $periodeSik->status) === 'Aktif' ? 'selected' : '' }}>
                                    Aktif
                                </option>

                                <option value="Selesai" {{ old('status', $periodeSik->status) === 'Selesai' ? 'selected' : '' }}>
                                    Selesai
                                </option>

                            </select>

                            @error('status')
                                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>

                    <div class="flex justify-end gap-3 mt-8 pt-5 border-t border-gray-200">

                        <a href="{{ route('periode-sik.index') }}"
                           class="px-5 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Batal
                        </a>

                        <button type="submit"
                                class="px-5 py-2.5 bg-[#800020] text-white rounded-lg hover:bg-[#650019]">
                            Perbarui Periode
                        </button>

                    </div>

                </form>

            </div>
        </div>
    </div>
</x-app-layout>
