<x-guest-layout>
    <div class="sik-login-card">
        <a class="sik-login-brand" href="{{ route('beranda') }}">
            <img src="{{ asset('images/logo-fst.png') }}" alt="Logo Fakultas Sains dan Teknologi">
            <span><strong>SIKVERSE</strong><small>Fakultas Sains &amp; Teknologi</small></span>
        </a>

        <header class="sik-login-heading">
            <h2>Verifikasi alamat email</h2>
            <p>Konfirmasi alamat email agar akun dapat digunakan.</p>
        </header>

        @if (session('status') === 'verification-code-sent')
            <div class="sik-auth-notice" role="status">Kode verifikasi baru sudah dikirim ke email Anda.</div>
        @endif

        <p class="sik-auth-description">
            Kami mengirim kode verifikasi ke <strong>{{ auth()->user()->email }}</strong>.
            Masukkan kode enam digit untuk melanjutkan. Kode berlaku selama 10 menit.
        </p>

        @if (app()->environment('local') && config('mail.default') === 'log')
            <p class="sik-auth-hint">Mode lokal belum mengirim email ke Gmail. Buka <code>storage/logs/laravel.log</code> di folder proyek untuk melihat kode OTP terakhir.</p>
        @endif

        <form method="POST" action="{{ route('verification.code.verify') }}" class="sik-login-form">
            @csrf
            <label class="sik-login-field">
                <span class="sik-login-label">Kode OTP</span>
                <input class="sik-login-control" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
            </label>
            @error('code')<p class="sik-auth-error" role="alert">{{ $message }}</p>@enderror
            <button class="sik-login-submit" type="submit">Verifikasi email</button>
        </form>

        <form method="POST" action="{{ route('verification.send') }}" class="sik-login-form">
            @csrf
            <button class="sik-login-submit" type="submit">Kirim ulang kode</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="sik-auth-logout">
            @csrf
            <button type="submit">Keluar</button>
        </form>
    </div>
</x-guest-layout>
