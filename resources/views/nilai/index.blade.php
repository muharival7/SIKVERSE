@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('Css/nilai.css') }}?v={{ filemtime(public_path('Css/nilai.css')) }}">
@endpush

@section('content')
<div class="container-fluid py-4 nilai-page">
    <div class="nilai-header">
        <div>
            <span class="nilai-eyebrow">SIKVERSE · Akademik</span>
            <h1 class="h3 fw-bold mb-0">Input dan Pengelolaan Nilai</h1>
        </div>
    </div>

    <form class="card border-0 shadow-sm nilai-filter-card mb-4" method="GET" action="{{ route('nilai.index') }}">
        <div class="card-body">
            <div class="nilai-filter">
                <div class="nilai-filter-field">
                    <label class="form-label" for="nilai-search">Cari santri</label>
                    <input class="form-control" id="nilai-search" type="search" name="q" value="{{ $search }}" placeholder="Cari nama atau NIM santri">
                    <div class="form-text">Cari berdasarkan nama atau NIM.</div>
                </div>
                <div class="nilai-filter-field">
                    <x-kelompok-multiselect
                        :kelompoks="$kelompoks"
                        :selected-groups="$selectedGroupIds"
                        id="nilai-group"
                        label="Kelompok tanggung jawab"
                        helper="Pilih satu atau beberapa kelompok."
                        :allow-all="false"
                        empty-label="Kelompok saya"
                        empty-badge="Aktif"
                        empty-helper="Hanya kelompok aktif tanggung jawab Anda."
                    />
                </div>
                <div class="nilai-filter-actions">
                    <button class="btn btn-primary" type="submit">Terapkan</button>
                    @if ($search !== '' || $selectedGroupIds !== [])
                        <a class="btn btn-outline-secondary" href="{{ route('nilai.index') }}">Reset</a>
                    @endif
                </div>
            </div>
        </div>
    </form>

    <datalist id="mapel-options">
        @foreach ($mataPelajaran as $mapel)
            <option value="{{ $mapel->nama_mapel }}"></option>
        @endforeach
    </datalist>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @forelse ($santris as $santri)
        <section class="card border-0 shadow-sm nilai-student-card mb-4">
            <div class="card-body">
                @php
                    $oldSantriId = (int) old('santri_id');
                    $isOldSantri = $oldSantriId === (int) $santri->id;
                    $candidates = collect($santri->anggota_periode_nilai_candidates ?? []);
                    $selectedAnggotaId = $isOldSantri
                        ? old('anggota_periode_sik_id', $santri->anggota_periode_nilai_id)
                        : $santri->anggota_periode_nilai_id;
                    $selectedAnggotaId = filled($selectedAnggotaId) ? (int) $selectedAnggotaId : null;
                    $selectedCandidate = $selectedAnggotaId ? $candidates->firstWhere('id', $selectedAnggotaId) : null;
                    $eligibilitasUjian = $selectedCandidate['exam_eligibility']
                        ?? ($santri->exam_eligibility ?? ['eligible' => false, 'alasan' => 'Kelayakan ujian belum dapat dihitung.']);
                    $candidatePayload = $candidates->values();
                    $isAmbiguous = $candidates->count() > 1;
                @endphp
                <div class="nilai-student-head">
                    <div class="nilai-student-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($santri->nama, 0, 1)) }}</div>
                    <div class="nilai-student-identity">
                        <h2 class="h5 fw-bold mb-0">{{ $santri->nama }}</h2>
                        <div class="nilai-student-meta">
                            <span class="nilai-meta-chip">
                                <span class="nilai-meta-label">NIM</span>
                                <span>{{ $santri->nomor_induk }}</span>
                            </span>
                            <span class="nilai-meta-chip">
                                <span class="nilai-meta-label">Konteks periode</span>
                                <span class="nilai-konteks-label" data-santri="{{ $santri->id }}">
                                    @if ($selectedCandidate)
                                        {{ $selectedCandidate['label'] ?? 'Periode · Kelompok' }}
                                    @elseif ($isAmbiguous)
                                        Pilih konteks periode/kelompok
                                    @else
                                        Tidak ada konteks aktif
                                    @endif
                                </span>
                            </span>
                        </div>
                    </div>
                    <span class="badge nilai-eligibility {{ ($eligibilitasUjian['eligible'] ?? false) ? 'bg-success' : 'bg-secondary' }}" data-eligibility-badge="{{ $santri->id }}">
                        Kelayakan ujian: {{ ($eligibilitasUjian['eligible'] ?? false) ? 'Memenuhi' : 'Tidak Memenuhi' }}
                    </span>
                </div>

                <div class="small text-muted mb-3" data-eligibility-reason="{{ $santri->id }}">
                    {{ $eligibilitasUjian['alasan'] ?? 'Kelayakan ujian belum dapat dihitung.' }}
                </div>

                @if ($candidates->isEmpty())
                    <div class="alert alert-warning mb-3">Santri ini tidak memiliki konteks keanggotaan aktif yang dapat Anda nilai.</div>
                @endif

                <div class="nilai-section-title">Nilai yang Anda kelola</div>
                @forelse ($santri->nilai as $catatan)
                    @php
                        $formOld = $isOldSantri && (string) old('_method') === 'PUT' && (string) old('nilai_id') === (string) $catatan->id;
                    @endphp
                    <form method="POST" action="{{ route('nilai.update', $catatan) }}" class="nilai-entry-form">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="santri_id" value="{{ $santri->id }}">
                        <input type="hidden" name="nilai_id" value="{{ $catatan->id }}">
                        <div class="nilai-form-grid">
                            <div class="nilai-field nilai-field--konteks">
                                <label class="form-label" for="konteks-{{ $catatan->id }}">Konteks periode/kelompok</label>
                                @php
                                    $editSelectedAnggotaId = $formOld ? old('anggota_periode_sik_id', $selectedAnggotaId) : $selectedAnggotaId;
                                    $editSelectedAnggotaId = filled($editSelectedAnggotaId) ? (int) $editSelectedAnggotaId : null;
                                @endphp
                                @if ($isAmbiguous)
                                    <select class="form-select {{ $formOld && $errors->has('anggota_periode_sik_id') ? 'is-invalid' : '' }}" id="konteks-{{ $catatan->id }}" name="anggota_periode_sik_id" data-anggota-context="{{ $santri->id }}" data-candidates='@json($candidatePayload)' required>
                                        <option value="">Pilih konteks periode/kelompok</option>
                                        @foreach ($candidates as $candidate)
                                            <option value="{{ $candidate['id'] }}" @selected((string) $editSelectedAnggotaId === (string) $candidate['id'])>
                                                {{ $candidate['label'] ?? 'Periode · Kelompok' }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="hidden" name="anggota_periode_sik_id" value="{{ $editSelectedAnggotaId }}" data-anggota-context="{{ $santri->id }}" data-candidates='@json($candidatePayload)'>
                                    <input class="form-control" id="konteks-{{ $catatan->id }}" type="text" value="{{ collect($candidates)->firstWhere('id', $editSelectedAnggotaId)['label'] ?? ($selectedCandidate['label'] ?? 'Konteks tidak tersedia') }}" disabled>
                                @endif
                                @if ($formOld)
                                    @error('anggota_periode_sik_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>
                            <div class="nilai-field nilai-field--mapel">
                                <label class="form-label" for="mapel-{{ $catatan->id }}">Mata pelajaran</label>
                                <input class="form-control {{ $formOld && $errors->has('nama_mapel') ? 'is-invalid' : '' }}" id="mapel-{{ $catatan->id }}" name="nama_mapel" list="mapel-options" value="{{ $formOld ? old('nama_mapel') : $catatan->mataPelajaran?->nama_mapel }}" placeholder="Masukkan nama mata pelajaran" maxlength="255" required>
                                @if ($formOld)
                                    @error('nama_mapel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @endif
                            </div>
                            <div class="nilai-field nilai-field--jenis">
                                <label class="form-label" for="jenis-{{ $catatan->id }}">Jenis nilai</label>
                                <select class="form-select {{ $formOld && $errors->has('jenis_nilai') ? 'is-invalid' : '' }}" id="jenis-{{ $catatan->id }}" name="jenis_nilai">
                                    @foreach (['Harian', 'Ujian'] as $jenis)
                                        <option value="{{ $jenis }}" @selected(($formOld ? old('jenis_nilai', $catatan->jenis_nilai ?? 'Harian') : ($catatan->jenis_nilai ?? 'Harian')) === $jenis)>{{ $jenis }}</option>
                                    @endforeach
                                </select>
                                @if ($formOld)
                                    @error('jenis_nilai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @endif
                            </div>
                            <div class="nilai-field nilai-field--kegiatan">
                                <label class="form-label" for="kegiatan-{{ $catatan->id }}">Nama kegiatan SIK</label>
                                <input class="form-control {{ $formOld && $errors->has('nama_kegiatan') ? 'is-invalid' : '' }}" id="kegiatan-{{ $catatan->id }}" name="nama_kegiatan" value="{{ $formOld ? old('nama_kegiatan') : ($catatan->nama_kegiatan ?? $catatan->kegiatan?->nama_kegiatan) }}" maxlength="150" required>
                                <div class="form-text">Ketik nama kegiatan. Input ini tidak membuat master kegiatan baru.</div>
                                @if ($formOld)
                                    @error('nama_kegiatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @endif
                            </div>
                            <div class="nilai-field nilai-field--semester">
                                <label class="form-label" for="semester-{{ $catatan->id }}">Semester</label>
                                <input class="form-control {{ $formOld && $errors->has('semester') ? 'is-invalid' : '' }}" id="semester-{{ $catatan->id }}" name="semester" value="{{ $formOld ? old('semester') : $catatan->semester }}" maxlength="50" required>
                                @if ($formOld)
                                    @error('semester')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @endif
                            </div>
                            <div class="nilai-field nilai-field--tahun">
                                <label class="form-label" for="tahun-{{ $catatan->id }}">Tahun ajaran</label>
                                <input class="form-control {{ $formOld && $errors->has('tahun_ajaran') ? 'is-invalid' : '' }}" id="tahun-{{ $catatan->id }}" name="tahun_ajaran" value="{{ $formOld ? old('tahun_ajaran') : $catatan->tahun_ajaran }}" maxlength="20" required>
                                @if ($formOld)
                                    @error('tahun_ajaran')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @endif
                            </div>
                            <div class="nilai-field nilai-field--nilai">
                                <label class="form-label" for="nilai-{{ $catatan->id }}">Nilai (0–100)</label>
                                <input class="form-control {{ $formOld && $errors->has('nilai') ? 'is-invalid' : '' }}" id="nilai-{{ $catatan->id }}" type="number" name="nilai" min="0" max="100" step="0.01" value="{{ $formOld ? old('nilai') : $catatan->nilai }}" required>
                                @if ($formOld)
                                    @error('nilai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @endif
                            </div>
                            <div class="nilai-field nilai-field--keterangan">
                                <label class="form-label" for="keterangan-{{ $catatan->id }}">Keterangan</label>
                                <textarea class="form-control {{ $formOld && $errors->has('keterangan') ? 'is-invalid' : '' }}" id="keterangan-{{ $catatan->id }}" name="keterangan" rows="2" maxlength="1000">{{ $formOld ? old('keterangan') : $catatan->keterangan }}</textarea>
                                @if ($formOld)
                                    @error('keterangan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @endif
                            </div>
                        </div>
                        <div class="nilai-form-footer">
                            <button class="btn btn-primary" type="submit" @disabled($candidates->isEmpty())>Simpan Nilai</button>
                        </div>
                    </form>
                @empty
                    <p class="text-muted mb-0">Belum ada nilai yang dicatat oleh Anda.</p>
                @endforelse

                <details class="nilai-add-details" @if($isOldSantri && (string) old('_method') !== 'PUT') open @endif>
                    <summary>Tambah nilai</summary>
                    <form method="POST" action="{{ route('nilai.store') }}" class="nilai-entry-form nilai-entry-form--create">
                        @csrf
                        <input type="hidden" name="santri_id" value="{{ $santri->id }}">
                        <div class="nilai-form-grid">
                            <div class="nilai-field nilai-field--konteks">
                                <label class="form-label" for="konteks-baru-{{ $santri->id }}">Konteks periode/kelompok</label>
                                @php
                                    $createOld = $isOldSantri && (string) old('_method') !== 'PUT';
                                    $createSelectedAnggotaId = $createOld ? old('anggota_periode_sik_id', $selectedAnggotaId) : $selectedAnggotaId;
                                    $createSelectedAnggotaId = filled($createSelectedAnggotaId) ? (int) $createSelectedAnggotaId : null;
                                @endphp
                                @if ($isAmbiguous)
                                    <select class="form-select {{ $createOld && $errors->has('anggota_periode_sik_id') ? 'is-invalid' : '' }}" id="konteks-baru-{{ $santri->id }}" name="anggota_periode_sik_id" data-anggota-context="{{ $santri->id }}" data-candidates='@json($candidatePayload)' required>
                                        <option value="">Pilih konteks periode/kelompok</option>
                                        @foreach ($candidates as $candidate)
                                            <option value="{{ $candidate['id'] }}" @selected((string) $createSelectedAnggotaId === (string) $candidate['id'])>
                                                {{ $candidate['label'] ?? 'Periode · Kelompok' }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="hidden" name="anggota_periode_sik_id" value="{{ $createSelectedAnggotaId }}" data-anggota-context="{{ $santri->id }}" data-candidates='@json($candidatePayload)'>
                                    <input class="form-control" id="konteks-baru-{{ $santri->id }}" type="text" value="{{ collect($candidates)->firstWhere('id', $createSelectedAnggotaId)['label'] ?? ($selectedCandidate['label'] ?? 'Konteks tidak tersedia') }}" disabled>
                                @endif
                                @if ($createOld)
                                    @error('anggota_periode_sik_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>
                            <div class="nilai-field nilai-field--mapel">
                                <label class="form-label" for="mapel-baru-{{ $santri->id }}">Mata pelajaran</label>
                                <input class="form-control {{ $createOld && $errors->has('nama_mapel') ? 'is-invalid' : '' }}" id="mapel-baru-{{ $santri->id }}" name="nama_mapel" list="mapel-options" value="{{ $createOld ? old('nama_mapel') : '' }}" placeholder="Masukkan nama mata pelajaran" maxlength="255" required>
                                @if ($createOld)
                                    @error('nama_mapel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @endif
                            </div>
                            <div class="nilai-field nilai-field--jenis">
                                <label class="form-label" for="jenis-baru-{{ $santri->id }}">Jenis nilai</label>
                                <select class="form-select {{ $createOld && $errors->has('jenis_nilai') ? 'is-invalid' : '' }}" id="jenis-baru-{{ $santri->id }}" name="jenis_nilai">
                                    @foreach (['Harian', 'Ujian'] as $jenis)
                                        <option value="{{ $jenis }}" @selected(($createOld ? old('jenis_nilai', 'Harian') : 'Harian') === $jenis)>{{ $jenis }}</option>
                                    @endforeach
                                </select>
                                @if ($createOld)
                                    @error('jenis_nilai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @endif
                            </div>
                            <div class="nilai-field nilai-field--kegiatan">
                                <label class="form-label" for="kegiatan-baru-{{ $santri->id }}">Nama kegiatan SIK</label>
                                <input class="form-control {{ $createOld && $errors->has('nama_kegiatan') ? 'is-invalid' : '' }}" id="kegiatan-baru-{{ $santri->id }}" name="nama_kegiatan" value="{{ $createOld ? old('nama_kegiatan') : '' }}" maxlength="150" required>
                                <div class="form-text">Ketik nama kegiatan. Input ini tidak membuat master kegiatan baru.</div>
                                @if ($createOld)
                                    @error('nama_kegiatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @endif
                            </div>
                            <div class="nilai-field nilai-field--semester">
                                <label class="form-label" for="semester-baru-{{ $santri->id }}">Semester</label>
                                <input class="form-control {{ $createOld && $errors->has('semester') ? 'is-invalid' : '' }}" id="semester-baru-{{ $santri->id }}" name="semester" value="{{ $createOld ? old('semester') : '' }}" maxlength="50" required>
                                @if ($createOld)
                                    @error('semester')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @endif
                            </div>
                            <div class="nilai-field nilai-field--tahun">
                                <label class="form-label" for="tahun-baru-{{ $santri->id }}">Tahun ajaran</label>
                                <input class="form-control {{ $createOld && $errors->has('tahun_ajaran') ? 'is-invalid' : '' }}" id="tahun-baru-{{ $santri->id }}" name="tahun_ajaran" value="{{ $createOld ? old('tahun_ajaran', '2026/2027') : '2026/2027' }}" maxlength="20" placeholder="2026/2027" required>
                                @if ($createOld)
                                    @error('tahun_ajaran')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @endif
                            </div>
                            <div class="nilai-field nilai-field--nilai">
                                <label class="form-label" for="nilai-baru-{{ $santri->id }}">Nilai (0–100)</label>
                                <input class="form-control {{ $createOld && $errors->has('nilai') ? 'is-invalid' : '' }}" id="nilai-baru-{{ $santri->id }}" type="number" name="nilai" min="0" max="100" step="0.01" value="{{ $createOld ? old('nilai') : '' }}" required>
                                @if ($createOld)
                                    @error('nilai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @endif
                            </div>
                            <div class="nilai-field nilai-field--keterangan">
                                <label class="form-label" for="keterangan-baru-{{ $santri->id }}">Keterangan</label>
                                <textarea class="form-control {{ $createOld && $errors->has('keterangan') ? 'is-invalid' : '' }}" id="keterangan-baru-{{ $santri->id }}" name="keterangan" rows="2" maxlength="1000">{{ $createOld ? old('keterangan') : '' }}</textarea>
                                @if ($createOld)
                                    @error('keterangan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @endif
                            </div>
                        </div>
                        <div class="nilai-form-footer">
                            <button class="btn btn-primary" type="submit" @disabled($candidates->isEmpty())>Simpan Nilai</button>
                        </div>
                    </form>
                </details>
            </div>
        </section>
    @empty
        <div class="alert alert-info nilai-empty">Belum ada santri aktif dalam kelompok yang Anda ampu.</div>
    @endforelse
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const updateEligibilityUi = function (santriId, field) {
        let candidates = [];
        try { candidates = JSON.parse(field.dataset.candidates || '[]'); } catch (e) { candidates = []; }
        const selected = candidates.find(c => String(c.id) === String(field.value));
        const badge = document.querySelector('[data-eligibility-badge="' + santriId + '"]');
        const reason = document.querySelector('[data-eligibility-reason="' + santriId + '"]');
        const label = document.querySelector('.nilai-konteks-label[data-santri="' + santriId + '"]');

        if (label) label.textContent = selected ? (selected.label || 'Konteks terpilih') : 'Pilih konteks periode/kelompok';
        if (!badge || !reason) return;

        if (!selected) {
            badge.classList.remove('bg-success');
            badge.classList.add('bg-secondary');
            badge.textContent = 'Kelayakan ujian: Tidak Memenuhi';
            reason.textContent = 'Pilih konteks periode/kelompok santri terlebih dahulu untuk mengevaluasi kelayakan ujian.';
            return;
        }

        const eligibility = selected.exam_eligibility || {};
        const eligible = !!eligibility.eligible;
        badge.classList.toggle('bg-success', eligible);
        badge.classList.toggle('bg-secondary', !eligible);
        badge.textContent = 'Kelayakan ujian: ' + (eligible ? 'Memenuhi' : 'Tidak Memenuhi');
        reason.textContent = eligibility.alasan || '';
    };

    document.querySelectorAll('[data-anggota-context]').forEach(function (field) {
        if (field.tagName !== 'SELECT') return;

        field.addEventListener('change', function () {
            const santriId = field.dataset.anggotaContext;
            const selectedValue = field.value;

            document.querySelectorAll('select[data-anggota-context="' + santriId + '"]').forEach(function (other) {
                if (other === field) return;
                const hasValue = Array.from(other.options).some(function (option) {
                    return String(option.value) === String(selectedValue);
                });
                if (hasValue) {
                    other.value = selectedValue;
                }
            });

            updateEligibilityUi(santriId, field);
        });
    });
});
</script>
@endpush
