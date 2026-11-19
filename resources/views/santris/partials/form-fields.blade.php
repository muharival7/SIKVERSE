<div class="row g-3">
    <div class="col-12 col-md-6">
        <label for="nomor_induk" class="form-label">Nomor Induk / NIM *</label>
        <input id="nomor_induk" name="nomor_induk" type="text" value="{{ old('nomor_induk', $santri?->nomor_induk) }}" class="form-control @error('nomor_induk') is-invalid @enderror" maxlength="255" inputmode="numeric" pattern="[0-9]*" title="Nomor Induk/NIM hanya boleh berisi angka 0-9." required autocomplete="off">
        @error('nomor_induk')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label for="nama" class="form-label">Nama Lengkap *</label>
        <input id="nama" name="nama" type="text" value="{{ old('nama', $santri?->nama) }}" class="form-control @error('nama') is-invalid @enderror" maxlength="255" required autocomplete="name">
        @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label for="email" class="form-label">Email akun {{ config('sik.auth.require_email_verification') ? '*' : '(opsional)' }}</label>
        <input id="email" name="email" type="email" value="{{ old('email', $santri?->user?->email) }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email" @required(config('sik.auth.require_email_verification'))>
        <div class="form-text">Dipakai untuk verifikasi dan pemulihan akun. Jika kosong, NIM digunakan sebagai username lokal.</div>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label for="program_studi" class="form-label">Program Studi</label>
        <input id="program_studi" name="program_studi" type="text" value="{{ old('program_studi', $santri?->program_studi) }}" class="form-control @error('program_studi') is-invalid @enderror" maxlength="255">
        @error('program_studi')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
        <select id="jenis_kelamin" name="jenis_kelamin" class="form-select @error('jenis_kelamin') is-invalid @enderror">
            <option value="">Pilih jenis kelamin</option>
            <option value="Laki-laki" @selected(old('jenis_kelamin', $santri?->jenis_kelamin) === 'Laki-laki')>Laki-laki</option>
            <option value="Perempuan" @selected(old('jenis_kelamin', $santri?->jenis_kelamin) === 'Perempuan')>Perempuan</option>
        </select>
        @error('jenis_kelamin')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label for="fingerprint_id" class="form-label">Fingerprint ID <span class="text-muted fw-normal">(opsional)</span></label>
        <input id="fingerprint_id" name="fingerprint_id" type="text" value="{{ old('fingerprint_id', $santri?->fingerprint?->fingerprint_id) }}" class="form-control @error('fingerprint_id') is-invalid @enderror" maxlength="255">
        @error('fingerprint_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label for="status" class="form-label">Status Santri *</label>
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            <option value="Aktif" @selected(old('status', $santri?->status ?? 'Aktif') === 'Aktif')>Aktif</option>
            <option value="Tidak Aktif" @selected(old('status', $santri?->status) === 'Tidak Aktif')>Tidak Aktif</option>
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
