
<x-guest-layout>
    <div class="mb-6 text-center">
        <h2 class="text-2xl font-bold text-gray-800">
            Ganti Password SIKVERSE
        </h2>
        <p class="mt-2 text-sm text-gray-600">
            Silakan ganti password sementara sebelum masuk ke dashboard.
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-100 p-3 text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('change-password.update') }}">
        @csrf

        <div>
            <x-input-label for="current_password" value="Password Lama" />
            <x-text-input
                id="current_password"
                name="current_password"
                type="password"
                class="mt-1 block w-full"
                required
                autocomplete="current-password"
            />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Password Baru" />
            <x-text-input
                id="password"
                name="password"
                type="password"
                class="mt-1 block w-full"
                required
                minlength="8"
                autocomplete="new-password"
            />
            <p class="mt-1 text-xs text-gray-500">
                Minimal 8 karakter.
            </p>
        </div>

        <div class="mt-4">
            <x-input-label
                for="password_confirmation"
                value="Konfirmasi Password Baru"
            />
            <x-text-input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                class="mt-1 block w-full"
                required
                autocomplete="new-password"
            />
        </div>

        <div class="mt-6">
            <x-primary-button class="w-full justify-center">
                Simpan Password
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
