<x-guest-layout>
    <div class="sik-login-card">
        <a class="sik-login-brand" href="{{ route('beranda') }}">
            <img src="{{ asset('images/logo-fst.png') }}" alt="Logo Fakultas Sains dan Teknologi">
            <span><strong>SIKVERSE</strong><small>Fakultas Sains &amp; Teknologi</small></span>
        </a>

        <header class="sik-login-heading">
            <h2>Buat kata sandi baru</h2>
            <p>Gunakan kata sandi baru untuk akun Anda.</p>
        </header>

        <form method="POST" action="{{ route('password.store') }}" class="sik-login-form">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <div class="sik-login-field">
                <label class="sik-login-label" for="email">Alamat email</label>
                <input class="sik-login-control" id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="email">
                @error('email')<p class="sik-login-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="sik-login-field">
                <label class="sik-login-label" for="password">Kata sandi baru</label>
                <input class="sik-login-control" id="password" type="password" name="password" required autocomplete="new-password" placeholder="Masukkan kata sandi baru">
                @error('password')<p class="sik-login-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="sik-login-field">
                <label class="sik-login-label" for="password_confirmation">Konfirmasi kata sandi</label>
                <input class="sik-login-control" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Ulangi kata sandi baru">
                @error('password_confirmation')<p class="sik-login-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <button class="sik-login-submit" type="submit">Simpan kata sandi</button>
        </form>
    </div>
</x-guest-layout>
