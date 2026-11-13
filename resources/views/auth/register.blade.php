<x-guest-layout>
    <div class="sik-login-card sik-register-card">
        <a class="sik-login-brand" href="{{ route('beranda') }}">
            <img src="{{ asset('images/logo-fst.png') }}" alt="Logo Fakultas Sains dan Teknologi">
            <span><strong>SIKVERSE</strong><small>Fakultas Sains &amp; Teknologi</small></span>
        </a>

        <header class="sik-login-heading">
            <h2>Daftar Ke SIKVERSE</h2>
            <p>Buat akun santri dengan data yang sesuai. Setelah terdaftar, Anda dapat masuk menggunakan NIM.</p>
        </header>

        @unless (config('sik.auth.require_email_verification'))
            <p class="sik-register-dev-note" role="status">
                Mode pengembangan: verifikasi email sementara tidak diwajibkan. Aktifkan verifikasi setelah pengiriman email resmi disiapkan.
            </p>
        @endunless

        <form method="POST" action="{{ route('register') }}" class="sik-login-form sik-register-form">
            @csrf

            <div class="sik-login-field sik-register-field">
                <label class="sik-login-label" for="email">Alamat Email</label>
                <input id="email" class="sik-login-control" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="Masukkan alamat email">
                @error('email')<p class="sik-login-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="sik-login-field sik-register-field">
                <label class="sik-login-label" for="name">Nama Lengkap</label>
                <input id="name" class="sik-login-control" type="text" name="name" value="{{ old('name') }}" required autocomplete="name" placeholder="Masukkan nama lengkap">
                @error('name')<p class="sik-login-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="sik-login-field sik-register-field">
                <label class="sik-login-label" for="nomor_induk">Nomor Induk Mahasiswa</label>
                <input id="nomor_induk" class="sik-login-control" type="text" name="nomor_induk" value="{{ old('nomor_induk') }}" required autocomplete="username" placeholder="Masukkan NIM">
                @error('nomor_induk')<p class="sik-login-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="sik-login-field sik-register-field">
                <label class="sik-login-label" for="program_studi">Program Studi</label>
                <input id="program_studi" class="sik-login-control" type="text" name="program_studi" value="{{ old('program_studi') }}" required autocomplete="organization-title" placeholder="Masukkan program studi">
                @error('program_studi')<p class="sik-login-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="sik-login-field sik-register-field">
                <label class="sik-login-label" for="password">Kata Sandi</label>
                <input id="password" class="sik-login-control" type="password" name="password" required autocomplete="new-password" placeholder="Buat kata sandi">
                @error('password')<p class="sik-login-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="sik-login-field sik-register-field">
                <label class="sik-login-label" for="password_confirmation">Konfirmasi Kata Sandi</label>
                <input id="password_confirmation" class="sik-login-control" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Ulangi kata sandi">
            </div>

            <button class="sik-login-submit" type="submit">Daftar</button>
        </form>

        <p class="sik-login-register">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
    </div>
</x-guest-layout>
