@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Css/absensi.css') }}?v={{ filemtime(public_path('Css/absensi.css')) }}">
@endpush

@section('content')
<div class="container-fluid py-4 px-4 sik-attendance-page">
    <header class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <span class="attendance-kicker">KEHADIRAN SIK</span>
            <h1 class="h2 fw-bold mb-1">Absensi Santri</h1>
            <p class="text-muted mb-0">Cari peserta, pilih kelompok, lalu perbarui status kehadiran per pertemuan.</p>
        </div>
        <span class="attendance-count">{{ number_format($absensi->total()) }} catatan</span>
    </header>

    @if (session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form class="attendance-filter card border-0 shadow-sm mb-3" method="GET" action="{{ route('absensi-sik.index') }}">
        <div class="attendance-filter-field">
            <label class="form-label small fw-semibold mb-1" for="attendance-search">Cari santri</label>
            <input class="form-control" id="attendance-search" type="search" name="q" value="{{ $search }}" placeholder="Nama atau NIM santri">
            <div class="form-text">Pencarian berlaku bersama periode dan kelompok.</div>
        </div>
        <div class="attendance-filter-field">
            <label class="form-label small fw-semibold mb-1" for="attendance-periode">Periode SIK</label>
            <select class="form-select" id="attendance-periode" name="periode_sik_id">
                <option value="">Semua periode</option>
                @foreach ($periodes as $periode)
                    <option value="{{ $periode->id }}" @selected((string) $selectedPeriodeId === (string) $periode->id)>{{ $periode->nama_periode }} · {{ $periode->tahun }}</option>
                @endforeach
            </select>
            <div class="form-text">Menentukan daftar kelompok yang tersedia.</div>
        </div>
        <x-kelompok-multiselect
            class="attendance-filter-field"
            :kelompoks="$kelompoks"
            :selected-groups="$selectedGroupIds"
            id="attendance-group"
            helper="Centang satu atau beberapa kelompok."
        />
        <div class="attendance-filter-actions">
            <button class="btn btn-primary" type="submit">Terapkan</button>
            @if ($search !== '' || $selectedPeriodeId !== null || $selectedGroupIds !== [])
                <a class="btn btn-outline-secondary" href="{{ route('absensi-sik.index') }}">Reset</a>
            @endif
        </div>
    </form>

    <details class="attendance-manual card border-0 shadow-sm mb-4" @if ($errors->has('pertemuan_id') || $errors->has('anggota_periode_sik_id')) open @endif>
        <summary><span>Catat absensi manual</span><span class="attendance-summary-note">Pilih pertemuan dan santri untuk membuat catatan baru</span></summary>
        <div class="card-body">
            <form action="{{ route('absensi-sik.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-lg-4"><label class="form-label" for="pertemuan_id">Pertemuan</label><select class="form-select @error('pertemuan_id') is-invalid @enderror" id="pertemuan_id" name="pertemuan_id" required><option value="">Pilih pertemuan</option>@foreach ($pertemuan as $item)<option value="{{ $item->id }}" @selected((string) old('pertemuan_id') === (string) $item->id)>{{ $item->kegiatan?->nama_kegiatan }} · ke-{{ $item->pertemuan_ke }} · {{ $item->tanggal?->format('d/m/Y') }}</option>@endforeach</select>@error('pertemuan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <p class="form-text mt-1">Tanggal kehadiran otomatis mengikuti tanggal Pertemuan yang dipilih. Untuk mengubah tanggal pertemuan yang sudah memiliki catatan absensi, gunakan menu “Ubah Tanggal Pertemuan” pada daftar kehadiran.</p>
                    </div>
                    <div class="col-lg-4"><label class="form-label" for="anggota_periode_sik_id">Santri</label><select class="form-select @error('anggota_periode_sik_id') is-invalid @enderror" id="anggota_periode_sik_id" name="anggota_periode_sik_id" required><option value="">Pilih santri</option>@foreach ($anggota as $member)<option value="{{ $member->id }}" @selected((string) old('anggota_periode_sik_id') === (string) $member->id)>{{ $member->santri?->nama }} · {{ $member->santri?->nomor_induk }} / {{ $member->kelompok?->nama_kelompok }}</option>@endforeach</select>@error('anggota_periode_sik_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-lg-4"><label class="form-label" for="new_status">Status</label><select class="form-select" id="new_status" name="status" required>@foreach (['Hadir', 'Izin', 'Sakit', 'Alpha', 'Tidak Lengkap'] as $status)<option value="{{ $status }}" @selected(old('status', 'Hadir') === $status)>{{ $status }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label" for="waktu_masuk">Waktu masuk</label><input class="form-control" type="time" id="waktu_masuk" name="waktu_masuk" value="{{ old('waktu_masuk') }}"></div>
                    <div class="col-md-3"><label class="form-label" for="waktu_pulang">Waktu pulang</label><input class="form-control" type="time" id="waktu_pulang" name="waktu_pulang" value="{{ old('waktu_pulang') }}"></div>
                    <div class="col-md-6"><label class="form-label" for="keterangan">Keterangan</label><input class="form-control" id="keterangan" name="keterangan" value="{{ old('keterangan') }}"></div>
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3"><p class="form-text mb-0">Pencatatan fingerprint belum terhubung; catatan ini disimpan sebagai absensi manual.</p><button class="btn btn-primary" type="submit">Simpan Absensi</button></div>
            </form>
        </div>
    </details>

    <section class="attendance-table-card card border-0 shadow-sm" aria-labelledby="attendance-table-title">
        <div class="card-header bg-white py-3"><h2 class="h5 fw-bold mb-0" id="attendance-table-title">Daftar Kehadiran</h2></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>No</th><th>Nomor Induk</th><th>Nama Santri</th><th>Kelompok</th><th>Pertemuan</th><th>Tanggal</th><th>Status / Perubahan</th></tr></thead>
                <tbody>
                    @forelse ($absensi as $item)
                        <tr>
                            <td>{{ $absensi->firstItem() + $loop->index }}</td>
                            <td class="text-nowrap">{{ $item->anggotaPeriode?->santri?->nomor_induk ?? '—' }}</td>
                            <td><strong>{{ $item->anggotaPeriode?->santri?->nama ?? '—' }}</strong></td>
                            <td>
                                <span class="text-nowrap">{{ $item->anggotaPeriode?->kelompok?->nama_kelompok ?? '—' }}</span>
                                <div class="small text-muted mt-1">{{ $item->anggotaPeriode?->periode?->nama_periode ?? 'Periode tidak diketahui' }}</div>
                            </td>
                            <td class="text-nowrap">
                                Ke-{{ $item->pertemuan?->pertemuan_ke ?? '—' }}
                                <div class="small text-muted mt-1">Kegiatan: {{ $item->pertemuan?->kegiatan?->nama_kegiatan ?? 'Kegiatan SIK' }}</div>
                                @if ($item->pertemuan && $item->pertemuan->status === 'Terjadwal' && in_array($item->pertemuan->id, $completablePertemuanIds, true))
                                    <form action="{{ route('absensi-sik.pertemuan.selesai', $item->pertemuan) }}" method="POST" class="mt-1">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success" type="submit">Tandai Selesai</button>
                                    </form>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                {{ $item->pertemuan?->tanggal?->format('d/m/Y') ?? '—' }}
                                @if ($item->pertemuan && $item->pertemuan->status !== 'Dibatalkan')
                                    @php
                                        $tanggalKelompok = $item->pertemuan->kelompokHost ?? $item->pertemuan->kegiatan?->kelompok;
                                        $tanggalPeriode = $item->pertemuan->kegiatan?->periode ?? $tanggalKelompok?->periode;
                                    @endphp
                                    <details class="attendance-date-change mt-1">
                                        <summary>Ubah Tanggal Pertemuan</summary>
                                        <form action="{{ route('absensi-sik.pertemuan.ubah-tanggal', $item->pertemuan) }}" method="POST" class="mt-2">
                                            @csrf
                                            @if ($tanggalKelompok)
                                                <input type="hidden" name="kelompok_id" value="{{ $tanggalKelompok->id }}">
                                            @endif
                                            @if ($tanggalPeriode)
                                                <input type="hidden" name="periode_sik_id" value="{{ $tanggalPeriode->id }}">
                                            @endif
                                            <div class="small text-muted mb-1">Tanggal saat ini: {{ $item->pertemuan->tanggal->format('d/m/Y') }}</div>
                                            <label class="visually-hidden" for="tanggal-baru-{{ $item->id }}">Tanggal baru pertemuan</label>
                                            <input class="form-control form-control-sm" id="tanggal-baru-{{ $item->id }}" type="date" name="tanggal_baru" value="{{ old('tanggal_baru', $item->pertemuan->tanggal->toDateString()) }}" required>
                                            <div class="form-check mt-1">
                                                <input class="form-check-input" type="checkbox" name="konfirmasi" value="1" id="konfirmasi-tanggal-{{ $item->id }}" required>
                                                <label class="form-check-label small" for="konfirmasi-tanggal-{{ $item->id }}">Saya yakin mengubah tanggal pertemuan ini</label>
                                            </div>
                                            <div class="d-flex gap-1 mt-2">
                                                <button class="btn btn-sm btn-outline-primary" type="submit">Simpan</button>
                                                <button class="btn btn-sm btn-outline-secondary" type="button" onclick="this.closest('details').open = false">Batal</button>
                                            </div>
                                            <div class="form-text mt-1">Tanggal baru tidak mengubah nomor pertemuan maupun catatan absensi.</div>
                                        </form>
                                    </details>
                                @endif
                            </td>
                            <td class="attendance-edit-cell">
                                <form action="{{ route('absensi-sik.update', $item) }}" method="POST" class="attendance-edit-form">
                                    @csrf @method('PUT')
                                    <div class="d-flex gap-2">
                                        <select class="form-select form-select-sm" name="status" aria-label="Status kehadiran">@foreach (['Hadir', 'Izin', 'Sakit', 'Alpha', 'Tidak Lengkap'] as $status)<option value="{{ $status }}" @selected($item->status === $status)>{{ $status }}</option>@endforeach</select>
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Simpan</button>
                                    </div>
                                    <details class="attendance-edit-details"><summary>Ubah waktu / keterangan</summary>
                                        <div class="row g-2 mt-1">
                                            <div class="col-6"><label class="form-label small" for="in-{{ $item->id }}">Masuk</label><input class="form-control form-control-sm" id="in-{{ $item->id }}" type="time" name="waktu_masuk" value="{{ $item->waktu_masuk ? substr($item->waktu_masuk, 0, 5) : '' }}"></div>
                                            <div class="col-6"><label class="form-label small" for="out-{{ $item->id }}">Pulang</label><input class="form-control form-control-sm" id="out-{{ $item->id }}" type="time" name="waktu_pulang" value="{{ $item->waktu_pulang ? substr($item->waktu_pulang, 0, 5) : '' }}"></div>
                                            <div class="col-12"><label class="form-label small" for="note-{{ $item->id }}">Keterangan</label><input class="form-control form-control-sm" id="note-{{ $item->id }}" name="keterangan" value="{{ $item->keterangan }}"></div>
                                            <div class="col-12"><label class="form-label small" for="reason-{{ $item->id }}">Alasan perubahan</label><input class="form-control form-control-sm" id="reason-{{ $item->id }}" name="alasan_perubahan"></div>
                                        </div>
                                    </details>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">Belum ada catatan absensi yang sesuai dengan pencarian, periode, dan kelompok yang dipilih.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($absensi->hasPages())<div class="card-footer bg-white">{{ $absensi->links() }}</div>@endif
    </section>
</div>
@endsection
