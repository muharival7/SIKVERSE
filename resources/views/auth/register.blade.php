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
                <div class="sik-login-control-wrap">
                    <x-sik-icon name="mail" class="sik-login-field-icon" />
                    <input id="email" @class(['sik-login-control', 'is-invalid' => $errors->has('email')]) type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="Masukkan alamat email" @if ($errors->has('email')) aria-describedby="register-email-error" aria-invalid="true" @endif>
                </div>
                @error('email')<p class="sik-login-error" id="register-email-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="sik-login-field sik-register-field">
                <label class="sik-login-label" for="name">Nama Lengkap</label>
                <div class="sik-login-control-wrap">
                    <x-sik-icon name="user" class="sik-login-field-icon" />
                    <input id="name" @class(['sik-login-control', 'is-invalid' => $errors->has('name')]) type="text" name="name" value="{{ old('name') }}" required autocomplete="name" placeholder="Masukkan nama lengkap" @if ($errors->has('name')) aria-describedby="register-name-error" aria-invalid="true" @endif>
                </div>
                @error('name')<p class="sik-login-error" id="register-name-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="sik-login-field sik-register-field">
                <label class="sik-login-label" for="nomor_induk">Nomor Induk Mahasiswa</label>
                <div class="sik-login-control-wrap">
                    <x-sik-icon name="id-card" class="sik-login-field-icon" />
                    <input id="nomor_induk" @class(['sik-login-control', 'is-invalid' => $errors->has('nomor_induk')]) type="text" name="nomor_induk" value="{{ old('nomor_induk') }}" required autocomplete="username" placeholder="Masukkan NIM" @if ($errors->has('nomor_induk')) aria-describedby="register-nim-error" aria-invalid="true" @endif>
                </div>
                @error('nomor_induk')<p class="sik-login-error" id="register-nim-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="sik-login-field sik-register-field">
                <label class="sik-login-label" for="program_studi">Program Studi</label>
                <div class="sik-login-control-wrap">
                    <x-sik-icon name="graduation" class="sik-login-field-icon" />
                    <input id="program_studi" @class(['sik-login-control', 'is-invalid' => $errors->has('program_studi')]) type="text" name="program_studi" value="{{ old('program_studi') }}" required autocomplete="organization-title" placeholder="Masukkan program studi" @if ($errors->has('program_studi')) aria-describedby="register-program-error" aria-invalid="true" @endif>
                </div>
                @error('program_studi')<p class="sik-login-error" id="register-program-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="sik-login-field sik-register-field">
                <label class="sik-login-label" for="jenis_kelamin">Jenis Kelamin</label>
                <div class="sik-login-control-wrap">
                    <x-sik-icon name="users" class="sik-login-field-icon" />
                    <select id="jenis_kelamin" @class(['sik-login-control', 'is-invalid' => $errors->has('jenis_kelamin')]) name="jenis_kelamin" required @if ($errors->has('jenis_kelamin')) aria-describedby="register-gender-error" aria-invalid="true" @endif>
                        <option value="">Pilih jenis kelamin</option>
                        <option value="Laki-laki" @selected(old('jenis_kelamin') === 'Laki-laki')>Laki-laki</option>
                        <option value="Perempuan" @selected(old('jenis_kelamin') === 'Perempuan')>Perempuan</option>
                    </select>
                </div>
                @error('jenis_kelamin')<p class="sik-login-error" id="register-gender-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="sik-login-field sik-register-field">
                <label class="sik-login-label" for="password">Kata Sandi</label>
                <div class="sik-login-control-wrap">
                    <x-sik-icon name="lock" class="sik-login-field-icon" />
                    <input id="password" @class(['sik-login-control', 'is-invalid' => $errors->has('password')]) type="password" name="password" required autocomplete="new-password" placeholder="Buat kata sandi" @if ($errors->has('password')) aria-describedby="register-password-error" aria-invalid="true" @endif>
                    <button class="sik-password-toggle" type="button" data-password-toggle aria-controls="password" aria-label="Tampilkan kata sandi" aria-pressed="false">
                        <x-sik-icon name="eye" data-eye-icon />
                        <x-sik-icon name="eye-off" data-eye-off-icon hidden />
                    </button>
                </div>
                @error('password')<p class="sik-login-error" id="register-password-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="sik-login-field sik-register-field">
                <label class="sik-login-label" for="password_confirmation">Konfirmasi Kata Sandi</label>
                <div class="sik-login-control-wrap">
                    <x-sik-icon name="lock-keyhole" class="sik-login-field-icon" />
                    <input id="password_confirmation" @class(['sik-login-control', 'is-invalid' => $errors->has('password_confirmation')]) type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Ulangi kata sandi" @if ($errors->has('password_confirmation')) aria-describedby="register-password-confirmation-error" aria-invalid="true" @endif>
                    <button class="sik-password-toggle" type="button" data-password-toggle aria-controls="password_confirmation" aria-label="Tampilkan kata sandi" aria-pressed="false">
                        <x-sik-icon name="eye" data-eye-icon />
                        <x-sik-icon name="eye-off" data-eye-off-icon hidden />
                    </button>
                </div>
                @error('password_confirmation')<p class="sik-login-error" id="register-password-confirmation-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <button class="sik-login-submit" type="submit">Daftar</button>
        </form>

        <p class="sik-login-register">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
    </div>
</x-guest-layout>
