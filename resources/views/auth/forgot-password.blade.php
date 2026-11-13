<x-guest-layout>
    <div class="sik-login-card">
        <a class="sik-login-brand" href="{{ route('beranda') }}">
            <img src="{{ asset('images/logo-fst.png') }}" alt="Logo Fakultas Sains dan Teknologi">
            <span><strong>SIKVERSE</strong><small>Fakultas Sains &amp; Teknologi</small></span>
        </a>

        <header class="sik-login-heading">
            <h2>Lupa kata sandi?</h2>
            <p>Masukkan email akun. Kami akan mengirim tautan untuk membuat kata sandi baru.</p>
        </header>

        <x-auth-session-status class="sik-login-status" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="sik-login-form">
            @csrf
            <div class="sik-login-field">
                <label class="sik-login-label" for="email">Alamat email</label>
                <input class="sik-login-control" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="Masukkan alamat email">
                @error('email')<p class="sik-login-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <button class="sik-login-submit" type="submit">Kirim tautan reset</button>
        </form>

        <p class="sik-login-register"><a href="{{ route('login') }}">Kembali ke login</a></p>
    </div>
</x-guest-layout>
