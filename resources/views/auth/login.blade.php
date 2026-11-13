<x-guest-layout>
    <div class="sik-login-card">
        <a class="sik-login-brand" href="{{ route('beranda') }}">
            <img src="{{ asset('images/logo-fst.png') }}" alt="Logo Fakultas Sains dan Teknologi">
            <span><strong>SIKVERSE</strong><small>Fakultas Sains &amp; Teknologi</small></span>
        </a>

        <header class="sik-login-heading">
            <h2>Masuk Ke SIKVERSE</h2>
            <p>Masukkan username dan kata sandi akun Anda.</p>
        </header>

        <x-auth-session-status class="sik-login-status" :status="session('status')" />

        <form method="POST" action="{{ route('login') }}" class="sik-login-form">
            @csrf

            <div class="sik-login-field">
                <label class="sik-login-label" for="login">Username</label>
                <input
                    id="login"
                    class="sik-login-control"
                    type="text"
                    name="login"
                    value="{{ old('login') }}"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="Masukkan username"
                    @if ($errors->has('login')) aria-describedby="login-error" aria-invalid="true" @endif
                >
                <x-sik-icon name="user" class="sik-login-field-icon" />
                @error('login')
                    <p class="sik-login-error" id="login-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="sik-login-field">
                <div class="sik-login-label-row">
                    <label class="sik-login-label" for="password">Kata Sandi</label>
                    <a class="sik-forgot-link" href="{{ route('password.request') }}">Lupa kata sandi?</a>
                </div>
                <input
                    id="password"
                    class="sik-login-control"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Masukkan kata sandi"
                    @if ($errors->has('password')) aria-describedby="password-error" aria-invalid="true" @endif
                >
                <x-sik-icon name="lock" class="sik-login-field-icon" />
                @error('password')
                    <p class="sik-login-error" id="password-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="sik-login-options">
                <label class="sik-remember" for="remember">
                    <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <span>Ingat saya</span>
                </label>
            </div>

            <button class="sik-login-submit" type="submit">Masuk</button>
        </form>

        @if (Route::has('register'))
            <p class="sik-login-register">Belum Punya Akun? <a href="{{ route('register') }}">Registrasi</a></p>
        @endif
    </div>
</x-guest-layout>
