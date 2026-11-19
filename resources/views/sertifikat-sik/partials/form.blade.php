<div class="row g-3">
    <div class="col-12">
        <label class="form-label" for="kelulusan_sik_id">Kelulusan Peserta</label>
<select class="form-select @error('kelulusan_sik_id') is-invalid @enderror"
        name="kelulusan_sik_id"
        id="kelulusan_sik_id"
        required>
    <option value="" disabled @selected(empty(old('kelulusan_sik_id', $item?->kelulusan_sik_id)))>Pilih kelulusan peserta</option>

    @foreach ($kelulusan as $result)
        <option value="{{ $result->id }}"
            @selected((string) old('kelulusan_sik_id', $item?->kelulusan_sik_id) === (string) $result->id)>
            {{ $result->anggotaPeriodeSik?->santri?->nomor_induk }}
            — {{ $result->anggotaPeriodeSik?->santri?->nama }}
            / {{ $result->anggotaPeriodeSik?->periode?->nama_periode }}
            · {{ $result->evaluasi_label }}
        </option>
    @endforeach
</select>        @error('kelulusan_sik_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Hanya catatan Lulus yang memenuhi ambang kehadiran yang dapat dipilih. Sertifikat yang sudah diterbitkan tetap dapat diedit.</div>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="nomor_sertifikat">Nomor Sertifikat</label>
        <input class="form-control @error('nomor_sertifikat') is-invalid @enderror" name="nomor_sertifikat" id="nomor_sertifikat" value="{{ old('nomor_sertifikat', $item?->nomor_sertifikat) }}" maxlength="255" required>
        @error('nomor_sertifikat')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="tanggal_terbit">Tanggal Terbit</label>
        <input class="form-control @error('tanggal_terbit') is-invalid @enderror" type="date" name="tanggal_terbit" id="tanggal_terbit" value="{{ old('tanggal_terbit', $item?->tanggal_terbit?->format('Y-m-d')) }}">
        @error('tanggal_terbit')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label" for="file_sertifikat">Berkas Sertifikat (PDF, maksimal 10 MB)</label>
        @if ($item?->file_sertifikat)
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2 p-3 border rounded bg-light">
                <span><strong>Berkas tersimpan</strong><small class="d-block text-muted">Unggah berkas baru hanya jika ingin menggantinya.</small></span>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('sertifikat-sik.download', $item) }}">Unduh berkas saat ini</a>
            </div>
        @endif
        <input class="form-control @error('file_sertifikat') is-invalid @enderror" type="file" accept="application/pdf,.pdf" name="file_sertifikat" id="file_sertifikat" @required(!$item)>
        <div class="form-text">Format PDF. Berkas disimpan privat dan hanya dapat diunduh oleh pengguna yang berwenang.</div>
        @error('file_sertifikat')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label" for="keterangan">Keterangan <span class="text-muted">(opsional)</span></label>
        <textarea class="form-control @error('keterangan') is-invalid @enderror" name="keterangan" id="keterangan" rows="3">{{ old('keterangan', $item?->keterangan) }}</textarea>
        @error('keterangan')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
