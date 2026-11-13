
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Edit Data Santri
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">

                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-6">
                    Perbarui Identitas Santri
                </h3>

                @if ($errors->any())
                    <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('santris.update', $santri) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                        <div>
                            <label class="block text-sm font-medium mb-2">Nomor Induk *</label>
                            <input type="text" name="nomor_induk"
                                value="{{ old('nomor_induk', $santri->nomor_induk) }}"
                                required
                                class="w-full rounded-lg border-gray-300 dark:bg-gray-700 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Nama Lengkap *</label>
                            <input type="text" name="nama"
                                value="{{ old('nama', $santri->nama) }}"
                                required
                                class="w-full rounded-lg border-gray-300 dark:bg-gray-700 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Email Akun {{ config('sik.auth.require_email_verification') ? '*' : '(opsional)' }}</label>
                            <input type="email" name="email"
                                value="{{ old('email', $santri->user?->email) }}"
                                @required(config('sik.auth.require_email_verification'))
                                autocomplete="email"
                                class="w-full rounded-lg border-gray-300 dark:bg-gray-700 dark:text-white">
                            <small class="text-gray-500">Dipakai untuk verifikasi OTP dan pemulihan akun.</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Program Studi</label>
                            <input type="text" name="program_studi"
                                value="{{ old('program_studi', $santri->program_studi) }}"
                                class="w-full rounded-lg border-gray-300 dark:bg-gray-700 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Jenis Kelamin</label>
                            <select name="jenis_kelamin"
                                class="w-full rounded-lg border-gray-300 dark:bg-gray-700 dark:text-white">
                                <option value="">Belum diisi</option>
                                <option value="Laki-laki"
                                    @selected(old('jenis_kelamin', $santri->jenis_kelamin) == 'Laki-laki')>
                                    Laki-laki
                                </option>
                                <option value="Perempuan"
                                    @selected(old('jenis_kelamin', $santri->jenis_kelamin) == 'Perempuan')>
                                    Perempuan
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Fingerprint ID</label>
                            <input type="text" name="fingerprint_id"
                                value="{{ old('fingerprint_id', $santri->fingerprint?->fingerprint_id) }}"
                                class="w-full rounded-lg border-gray-300 dark:bg-gray-700 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Status *</label>
                            <select name="status" required
                                class="w-full rounded-lg border-gray-300 dark:bg-gray-700 dark:text-white">
                                <option value="Aktif"
                                    @selected(old('status', $santri->status) == 'Aktif')>
                                    Aktif
                                </option>
                                <option value="Tidak Aktif"
                                    @selected(old('status', $santri->status) == 'Tidak Aktif')>
                                    Tidak Aktif
                                </option>
                            </select>
                        </div>

                    </div>

                    <div class="flex justify-end gap-3 mt-8">
                        <a href="{{ route('santris.index') }}"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                            Batal
                        </a>

                        <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                            Perbarui Data
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</x-app-layout>
