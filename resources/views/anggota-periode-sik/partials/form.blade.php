<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="santri_id">Santri</label>
        <select class="form-select @error('santri_id') is-invalid @enderror" id="santri_id" name="santri_id" required>
            <option value="">Pilih santri</option>
            @foreach ($santri as $person)
                <option value="{{ $person->id }}" @selected((string) old('santri_id', $item?->santri_id) === (string) $person->id)>{{ $person->nomor_induk }} — {{ $person->nama }}</option>
            @endforeach
        </select>
        @error('santri_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="periode_sik_id">Periode</label>
        <select class="form-select @error('periode_sik_id') is-invalid @enderror" id="periode_sik_id" name="periode_sik_id" required>
            <option value="">Pilih periode</option>
            @foreach ($periode as $period)
                <option value="{{ $period->id }}" @selected((string) old('periode_sik_id', $item?->periode_sik_id) === (string) $period->id)>{{ $period->nama_periode }} ({{ $period->tahun }})</option>
            @endforeach
        </select>
        @error('periode_sik_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="kelompok_id">Kelompok</label>
        <select class="form-select @error('kelompok_id') is-invalid @enderror" id="kelompok_id" name="kelompok_id" required>
            <option value="">Pilih kelompok untuk periode</option>
            @foreach ($kelompok as $group)
                <option value="{{ $group->id }}"
                        data-periode="{{ $group->periode_sik_id }}"
                        @selected((string) old('kelompok_id', $item?->kelompok_id) === (string) $group->id)>{{ $group->nama_kelompok }} — {{ $group->periode?->nama_periode }}</option>
            @endforeach
        </select>
        @error('kelompok_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="tanggal_masuk">Tanggal Masuk</label>
        <input class="form-control @error('tanggal_masuk') is-invalid @enderror" type="date" id="tanggal_masuk" name="tanggal_masuk" value="{{ old('tanggal_masuk', $item?->tanggal_masuk?->format('Y-m-d')) }}">
        @error('tanggal_masuk')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="status">Status</label>
        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
            @foreach (['Aktif', 'Tidak Aktif'] as $status)
                <option value="{{ $status }}" @selected(old('status', $item?->status ?? 'Aktif') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
