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
                    $keanggotaan = $santri->anggotaPeriode->first();
                    $eligibilitasUjian = $santri->exam_eligibility ?? ['eligible' => false, 'alasan' => 'Kelayakan ujian belum dapat dihitung.'];
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
                                <span class="nilai-meta-label">Kelompok</span>
                                <span>{{ $keanggotaan?->kelompok?->nama_kelompok ?? '—' }}</span>
                            </span>
                            <span class="nilai-meta-chip">
                                <span class="nilai-meta-label">Periode</span>
                                <span>{{ $keanggotaan?->periode?->nama_periode ?? '—' }}</span>
                            </span>
                        </div>
                    </div>
                    <span class="badge nilai-eligibility {{ $eligibilitasUjian['eligible'] ? 'bg-success' : 'bg-secondary' }}">
                        Kelayakan ujian: {{ $eligibilitasUjian['eligible'] ? 'Memenuhi' : 'Tidak Memenuhi' }}
                    </span>
                </div>

                <div class="nilai-section-title">Nilai yang Anda kelola</div>
                @forelse ($santri->nilai as $catatan)
                    <form method="POST" action="{{ route('nilai.update', $catatan) }}" class="nilai-entry-form">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="santri_id" value="{{ $santri->id }}">
                        <div class="nilai-form-grid">
                            <div class="nilai-field nilai-field--mapel">
                                <label class="form-label" for="mapel-{{ $catatan->id }}">Mata pelajaran</label>
                                <input class="form-control" id="mapel-{{ $catatan->id }}" name="nama_mapel" list="mapel-options" value="{{ old('nama_mapel', $catatan->mataPelajaran?->nama_mapel) }}" placeholder="Masukkan nama mata pelajaran" maxlength="255" required>
                            </div>
                            <div class="nilai-field nilai-field--jenis">
                                <label class="form-label" for="jenis-{{ $catatan->id }}">Jenis nilai</label>
                                <select class="form-select" id="jenis-{{ $catatan->id }}" name="jenis_nilai">
                                    @foreach (['Harian', 'Ujian'] as $jenis)
                                        <option value="{{ $jenis }}" @selected(old('jenis_nilai', $catatan->jenis_nilai ?? 'Harian') === $jenis)>{{ $jenis }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @php
                                $opsiKegiatan = $kegiatans;
                                if ($catatan->kegiatan_id !== null && ! $opsiKegiatan->contains('id', $catatan->kegiatan_id)) {
                                    $opsiKegiatan = $opsiKegiatan->concat([$catatan->kegiatan])->filter()->sortBy('nama_kegiatan');
                                }
                            @endphp
                            <div class="nilai-field nilai-field--kegiatan">
                                <label class="form-label" for="kegiatan-{{ $catatan->id }}">Kegiatan SIK</label>
                                <select class="form-select" id="kegiatan-{{ $catatan->id }}" name="kegiatan_id">
                                    <option value="" @selected(empty(old('kegiatan_id', $catatan->kegiatan_id)))>— Tidak ada —</option>
                                    @foreach ($opsiKegiatan as $kegiatan)
                                        <option value="{{ $kegiatan->id }}" @selected((string) old('kegiatan_id', $catatan->kegiatan_id) === (string) $kegiatan->id)>
                                            {{ $kegiatan->nama_kegiatan }} · {{ $kegiatan->periode?->nama_periode }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Wajib dipilih untuk nilai ujian akhir.</div>
                            </div>
                            <div class="nilai-field nilai-field--semester">
                                <label class="form-label" for="semester-{{ $catatan->id }}">Semester</label>
                                <input class="form-control" id="semester-{{ $catatan->id }}" name="semester" value="{{ old('semester', $catatan->semester) }}" maxlength="50" required>
                            </div>
                            <div class="nilai-field nilai-field--tahun">
                                <label class="form-label" for="tahun-{{ $catatan->id }}">Tahun ajaran</label>
                                <input class="form-control" id="tahun-{{ $catatan->id }}" name="tahun_ajaran" value="{{ old('tahun_ajaran', $catatan->tahun_ajaran) }}" maxlength="20" required>
                            </div>
                            <div class="nilai-field nilai-field--nilai">
                                <label class="form-label" for="nilai-{{ $catatan->id }}">Nilai (0–100)</label>
                                <input class="form-control" id="nilai-{{ $catatan->id }}" type="number" name="nilai" min="0" max="100" step="0.01" value="{{ old('nilai', $catatan->nilai) }}" required>
                            </div>
                            <div class="nilai-field nilai-field--keterangan">
                                <label class="form-label" for="keterangan-{{ $catatan->id }}">Keterangan</label>
                                <textarea class="form-control" id="keterangan-{{ $catatan->id }}" name="keterangan" rows="2" maxlength="1000">{{ old('keterangan', $catatan->keterangan) }}</textarea>
                            </div>
                        </div>
                        <div class="nilai-form-footer">
                            <button class="btn btn-primary" type="submit">Simpan Nilai</button>
                        </div>
                    </form>
                @empty
                    <p class="text-muted mb-0">Belum ada nilai yang dicatat oleh Anda.</p>
                @endforelse

                <details class="nilai-add-details">
                    <summary>Tambah nilai</summary>
                    <form method="POST" action="{{ route('nilai.store') }}" class="nilai-entry-form nilai-entry-form--create">
                        @csrf
                        <input type="hidden" name="santri_id" value="{{ $santri->id }}">
                        <div class="nilai-form-grid">
                            <div class="nilai-field nilai-field--mapel">
                                <label class="form-label" for="mapel-baru-{{ $santri->id }}">Mata pelajaran</label>
                                <input class="form-control" id="mapel-baru-{{ $santri->id }}" name="nama_mapel" list="mapel-options" value="{{ old('nama_mapel') }}" placeholder="Masukkan nama mata pelajaran" maxlength="255" required>
                            </div>
                            <div class="nilai-field nilai-field--jenis">
                                <label class="form-label" for="jenis-baru-{{ $santri->id }}">Jenis nilai</label>
                                <select class="form-select" id="jenis-baru-{{ $santri->id }}" name="jenis_nilai">
                                    @foreach (['Harian', 'Ujian'] as $jenis)
                                        <option value="{{ $jenis }}" @selected(old('jenis_nilai', 'Harian') === $jenis)>{{ $jenis }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="nilai-field nilai-field--kegiatan">
                                <label class="form-label" for="kegiatan-baru-{{ $santri->id }}">Kegiatan SIK</label>
                                <select class="form-select" id="kegiatan-baru-{{ $santri->id }}" name="kegiatan_id">
                                    <option value="" @selected(empty(old('kegiatan_id')))>— Tidak ada —</option>
                                    @foreach ($kegiatans as $kegiatan)
                                        <option value="{{ $kegiatan->id }}" @selected((string) old('kegiatan_id') === (string) $kegiatan->id)>
                                            {{ $kegiatan->nama_kegiatan }} · {{ $kegiatan->periode?->nama_periode }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Wajib dipilih untuk nilai ujian akhir.</div>
                            </div>
                            <div class="nilai-field nilai-field--semester">
                                <label class="form-label" for="semester-baru-{{ $santri->id }}">Semester</label>
                                <input class="form-control" id="semester-baru-{{ $santri->id }}" name="semester" value="{{ old('semester') }}" maxlength="50" required>
                            </div>
                            <div class="nilai-field nilai-field--tahun">
                                <label class="form-label" for="tahun-baru-{{ $santri->id }}">Tahun ajaran</label>
                                <input class="form-control" id="tahun-baru-{{ $santri->id }}" name="tahun_ajaran" value="{{ old('tahun_ajaran', '2026/2027') }}" maxlength="20" placeholder="2026/2027" required>
                            </div>
                            <div class="nilai-field nilai-field--nilai">
                                <label class="form-label" for="nilai-baru-{{ $santri->id }}">Nilai (0–100)</label>
                                <input class="form-control" id="nilai-baru-{{ $santri->id }}" type="number" name="nilai" min="0" max="100" step="0.01" value="{{ old('nilai') }}" required>
                            </div>
                            <div class="nilai-field nilai-field--keterangan">
                                <label class="form-label" for="keterangan-baru-{{ $santri->id }}">Keterangan</label>
                                <textarea class="form-control" id="keterangan-baru-{{ $santri->id }}" name="keterangan" rows="2" maxlength="1000">{{ old('keterangan') }}</textarea>
                            </div>
                        </div>
                        <div class="nilai-form-footer">
                            <button class="btn btn-primary" type="submit">Simpan Nilai</button>
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
