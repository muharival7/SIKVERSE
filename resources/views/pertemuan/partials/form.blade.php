<div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="kegiatan_id">Kegiatan</label><select class="form-select @error('kegiatan_id') is-invalid @enderror" name="kegiatan_id" id="kegiatan_id" required><option value="">Pilih kegiatan</option>@foreach ($kegiatan as $activity)<option value="{{ $activity->id }}" data-periode="{{ $activity->periode_sik_id }}" data-kelompok="{{ $activity->kelompok_id }}" data-shared="{{ in_array($activity->id, $sharedActivityIds, true) ? '1' : '0' }}" @selected((string) old('kegiatan_id', $item?->kegiatan_id) === (string) $activity->id)>{{ $activity->nama_kegiatan }} — {{ $activity->periode?->nama_periode }}</option>@endforeach</select>@error('kegiatan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-6"><label class="form-label" for="pertemuan_ke">Nomor Pertemuan</label><input class="form-control @error('pertemuan_ke') is-invalid @enderror" type="number" min="1" max="{{ config('sik.attendance.meeting_count', 16) }}" name="pertemuan_ke" id="pertemuan_ke" value="{{ old('pertemuan_ke', $item?->pertemuan_ke) }}" required>@error('pertemuan_ke')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-6"><label class="form-label" for="tanggal">Tanggal</label><input class="form-control @error('tanggal') is-invalid @enderror" type="date" name="tanggal" id="tanggal" value="{{ old('tanggal', $item?->tanggal?->format('Y-m-d')) }}" required>@error('tanggal')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label class="form-label" for="waktu_mulai">Waktu Mulai</label><input class="form-control @error('waktu_mulai') is-invalid @enderror" type="time" name="waktu_mulai" id="waktu_mulai" value="{{ old('waktu_mulai', $item?->waktu_mulai) }}">@error('waktu_mulai')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label class="form-label" for="waktu_selesai">Waktu Selesai</label><input class="form-control @error('waktu_selesai') is-invalid @enderror" type="time" name="waktu_selesai" id="waktu_selesai" value="{{ old('waktu_selesai', $item?->waktu_selesai) }}">@error('waktu_selesai')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-6"><label class="form-label" for="instruktur_pengganti_id">Instruktur Pengganti</label><select class="form-select @error('instruktur_pengganti_id') is-invalid @enderror" name="instruktur_pengganti_id" id="instruktur_pengganti_id"><option value="">Tidak ada</option>@foreach ($instruktur as $person)<option value="{{ $person->id }}" @selected((string) old('instruktur_pengganti_id', $item?->instruktur_pengganti_id) === (string) $person->id)>{{ $person->nama }}</option>@endforeach</select>@error('instruktur_pengganti_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-6"><label class="form-label" for="kelompok_host_id">Kelompok Host</label><select class="form-select @error('kelompok_host_id') is-invalid @enderror" name="kelompok_host_id" id="kelompok_host_id"><option value="">Sesuai kelompok kegiatan</option>@foreach ($kelompok as $group)<option value="{{ $group->id }}" data-periode="{{ $group->periode_sik_id }}" @selected((string) old('kelompok_host_id', $item?->kelompok_host_id) === (string) $group->id)>{{ $group->nama_kelompok }} — {{ $group->periode?->nama_periode }}</option>@endforeach</select>@error('kelompok_host_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-6"><label class="form-label" for="status">Status</label><select class="form-select @error('status') is-invalid @enderror" name="status" id="status" required>@foreach (['Terjadwal', 'Terlaksana', 'Dibatalkan'] as $status)<option value="{{ $status }}" @selected(old('status', $item?->status ?? 'Terjadwal') === $status)>{{ $status }}</option>@endforeach</select>@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-12"><label class="form-label" for="keterangan">Keterangan</label><textarea class="form-control @error('keterangan') is-invalid @enderror" name="keterangan" id="keterangan" rows="3">{{ old('keterangan', $item?->keterangan) }}</textarea>@error('keterangan')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
</div>

@pushOnce('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const kegiatanSelect = document.getElementById('kegiatan_id');
        const kelompokSelect = document.getElementById('kelompok_host_id');

        if (!kegiatanSelect || !kelompokSelect) {
            return;
        }

        function sesuaikanKelompok() {
            const kegiatanTerpilih = kegiatanSelect.options[kegiatanSelect.selectedIndex];

            if (!kegiatanTerpilih || !kegiatanTerpilih.value) {
                return;
            }

            const periodeTerpilih = kegiatanTerpilih.dataset.periode;
            const shared = kegiatanTerpilih.dataset.shared === '1';
            const semuaOption = kelompokSelect.options[0];

            semuaOption.textContent = shared ? 'Semua Kelompok' : 'Sesuai kelompok kegiatan';
            kelompokSelect.disabled = shared;

            Array.from(kelompokSelect.options).forEach(function (option) {
                if (!option.value) {
                    return;
                }

                option.hidden = option.dataset.periode !== periodeTerpilih;
                option.disabled = shared || option.hidden;
            });

            if (shared) {
                kelompokSelect.value = '';

                return;
            }

            const kelompokKegiatan = kegiatanTerpilih.dataset.kelompok;
            const selected = kelompokSelect.options[kelompokSelect.selectedIndex];

            if (kelompokKegiatan) {
                kelompokSelect.value = kelompokKegiatan;
            } else if (selected && selected.hidden) {
                kelompokSelect.value = '';
            }
        }

        kegiatanSelect.addEventListener('change', sesuaikanKelompok);
        sesuaikanKelompok();
    });
</script>
@endpushOnce
