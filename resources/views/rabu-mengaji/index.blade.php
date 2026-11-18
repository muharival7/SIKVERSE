@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <span class="text-uppercase fw-bold small"
                  style="color:#760d12;letter-spacing:.14em">
                Kehadiran kegiatan
            </span>
            <h1 class="h2 fw-bold mt-1 mb-1">Rabu Mengaji</h1>
        <p class="text-muted mb-0">
            Data diambil dari kegiatan, pertemuan, dan pencatatan absensi santri yang sudah ada.
        </p>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-3">
        <a class="btn btn-outline-primary" href="{{ route('kegiatan.index') }}">
            Buka Data Kegiatan
        </a>
        <a class="btn btn-primary" href="{{ route('rabu-mengaji.import') }}">
            Import Excel
        </a>
    </div>
    </div>

    @php
        $totalPertemuan = (int) ($summary->total_pertemuan ?? 0);
        $totalHadir = (int) ($summary->total_hadir ?? 0);
        $persentase = $totalPertemuan > 0 ? round(($totalHadir / $totalPertemuan) * 100, 1) : 0;
    @endphp

    <div class="row g-3 mb-4">
        @foreach ([
            ['Total catatan', $totalPertemuan],
            ['Total Hadir', $totalHadir],
            ['Total Izin', (int) ($summary->total_izin ?? 0)],
            ['Total Sakit', (int) ($summary->total_sakit ?? 0)],
            ['Total Alpha', (int) ($summary->total_alpha ?? 0)],
            ['Persentase hadir', number_format($persentase, 1, ',', '.').'%'],
        ] as [$label, $value])
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="fs-4 fw-bold" style="color:#760d12">
                            {{ is_numeric($value) ? number_format($value) : $value }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('rabu-mengaji.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-5">
                    <label for="rabu-search" class="form-label">Cari nama santri</label>
                    <input id="rabu-search"
                           type="search"
                           name="search"
                           value="{{ $filters['search'] ?? '' }}"
                           class="form-control"
                           placeholder="Nama santri">
                </div>
                <div class="col-12 col-lg-3">
                    <label for="rabu-tanggal" class="form-label">Tanggal pelaksanaan</label>
                    <input id="rabu-tanggal"
                           type="date"
                           name="tanggal"
                           value="{{ $filters['tanggal'] ?? '' }}"
                           class="form-control">
                </div>
                <div class="col-12 col-lg-2">
                    <label for="rabu-kegiatan" class="form-label">Kegiatan</label>
                    <select id="rabu-kegiatan" name="kegiatan_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach ($kegiatan as $item)
                            <option value="{{ $item->id }}"
                                @selected((int) ($filters['kegiatan_id'] ?? 0) === $item->id)>
                                {{ $item->nama_kegiatan }} #{{ $item->id }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-2 d-flex gap-2">
                    <button class="btn btn-danger flex-grow-1" type="submit">Cari</button>
                    <a class="btn btn-outline-secondary" href="{{ route('rabu-mengaji.index') }}">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">Daftar kehadiran Rabu Mengaji</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>Nama Santri</th>
                        <th>Nomor Induk</th>
                        <th>Kelompok Asal</th>
                        <th>Tanggal Pelaksanaan</th>
                        <th>Kegiatan</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($absensi as $catatan)
                        @php
                            $santri = $catatan->anggotaPeriode?->santri;
                            $kelompok = $catatan->anggotaPeriode?->kelompok;
                        @endphp
                        <tr>
                            <td>{{ $absensi->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $santri?->nama ?? '—' }}</td>
                            <td>{{ $santri?->nomor_induk ?? '—' }}</td>
                            <td>{{ $kelompok?->nama_kelompok ?? $kelompok?->nama ?? '—' }}</td>
                            <td>{{ $catatan->pertemuan?->tanggal?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $catatan->pertemuan?->kegiatan?->nama_kegiatan ?? '—' }}</td>
                            <td>
                                <span class="badge rounded-pill text-bg-{{ $catatan->status === 'Hadir' ? 'success' : ($catatan->status === 'Alpha' ? 'danger' : 'warning') }}">
                                    {{ $catatan->status }}
                                </span>
                            </td>
                            <td class="text-center">
                                <a class="btn btn-sm btn-outline-primary"
                                   href="{{ route('rabu-mengaji.show', $catatan) }}">
                                    Detail
                                </a>
                                @if ($catatan->pertemuan?->status !== 'Dibatalkan')
                                    <a class="btn btn-sm btn-success ms-1"
                                       href="{{ route('rabu-mengaji.input', $catatan->pertemuan) }}">
                                        Input Kehadiran
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                Belum ada kehadiran Rabu Mengaji untuk filter terpilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($absensi->hasPages())
            <div class="card-footer bg-white">{{ $absensi->links() }}</div>
        @endif
    </div>

</div>
@endsection
