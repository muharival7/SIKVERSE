<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="nama_kegiatan">Nama Kegiatan</label>
        <input class="form-control @error('nama_kegiatan') is-invalid @enderror" id="nama_kegiatan" name="nama_kegiatan" value="{{ old('nama_kegiatan', $kegiatan?->nama_kegiatan) }}" maxlength="150" required>
        @error('nama_kegiatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="jenis_kegiatan">Jenis Kegiatan</label>
        <select class="form-select @error('jenis_kegiatan') is-invalid @enderror" id="jenis_kegiatan" name="jenis_kegiatan" required>
            <option value="">Pilih jenis</option>
            @foreach (['Kelas', 'Aula'] as $jenis)
                <option value="{{ $jenis }}" @selected(old('jenis_kegiatan', $kegiatan?->jenis_kegiatan) === $jenis)>{{ $jenis }}</option>
            @endforeach
        </select>
        @error('jenis_kegiatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="periode_sik_id">Periode</label>
        <select class="form-select @error('periode_sik_id') is-invalid @enderror" id="periode_sik_id" name="periode_sik_id" required>
            <option value="">Pilih periode</option>
            @foreach ($periodeSik as $item)
                <option value="{{ $item->id }}" @selected((string) old('periode_sik_id', $kegiatan?->periode_sik_id) === (string) $item->id)>{{ $item->nama_periode }} ({{ $item->tahun }})</option>
            @endforeach
        </select>
        @error('periode_sik_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="kelompok_id">Kelompok <span class="text-muted">(wajib untuk kegiatan kelas)</span></label>
        <select class="form-select @error('kelompok_id') is-invalid @enderror" id="kelompok_id" name="kelompok_id">
            <option value="">Semua Kelompok</option>
            @foreach ($kelompok as $item)
                <option value="{{ $item->id }}"
                        data-periode="{{ $item->periode_sik_id }}"
                        @selected((string) old('kelompok_id', $kegiatan?->kelompok_id) === (string) $item->id)>{{ $item->nama_kelompok }} — {{ $item->periode?->nama_periode }}</option>
            @endforeach
        </select>
        @error('kelompok_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="status">Status</label>
        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
            @foreach (['Aktif', 'Tidak Aktif'] as $status)
                <option value="{{ $status }}" @selected(old('status', $kegiatan?->status ?? 'Aktif') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label" for="keterangan">Keterangan</label>
        <textarea class="form-control @error('keterangan') is-invalid @enderror" id="keterangan" name="keterangan" rows="3">{{ old('keterangan', $kegiatan?->keterangan) }}</textarea>
        @error('keterangan')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

@pushOnce('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const periodeSelect = document.getElementById('periode_sik_id');
        const kelompokSelect = document.getElementById('kelompok_id');

        if (!periodeSelect || !kelompokSelect) {
            return;
        }

        function sesuaikanKelompok() {
            const periodeTerpilih = periodeSelect.value;

            Array.from(kelompokSelect.options).forEach(function (option) {
                if (!option.value) {
                    return;
                }

                option.hidden = periodeTerpilih !== '' && option.dataset.periode !== periodeTerpilih;
                option.disabled = option.hidden;
            });

            const selected = kelompokSelect.options[kelompokSelect.selectedIndex];
            if (selected && selected.hidden) {
                kelompokSelect.value = '';
            }
        }

        periodeSelect.addEventListener('change', sesuaikanKelompok);
        sesuaikanKelompok();
    });
</script>
@endpushOnce
