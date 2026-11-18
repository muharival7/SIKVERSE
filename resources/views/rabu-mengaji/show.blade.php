@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <span class="text-uppercase fw-bold small"
                  style="color:#760d12;letter-spacing:.14em">
                Detail Rabu Mengaji
            </span>
            <h1 class="h2 fw-bold mt-1 mb-1">
                {{ $catatan->anggotaPeriode?->santri?->nama ?? 'Santri' }}
            </h1>
            <p class="text-muted mb-0">
                {{ $kegiatan->nama_kegiatan }} · {{ $catatan->pertemuan?->tanggal?->format('d/m/Y') ?? '—' }}
            </p>
        </div>

        <a class="btn btn-outline-secondary" href="{{ route('rabu-mengaji.index') }}">
            Kembali ke Rabu Mengaji
        </a>

        @if ($catatan->pertemuan?->status !== 'Dibatalkan')
            <a class="btn btn-success" href="{{ route('rabu-mengaji.input', $catatan->pertemuan) }}">
                Input Kehadiran
            </a>
        @endif
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">Informasi kehadiran</h2>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-4">
                    <div class="text-muted small">Nama santri</div>
                    <div class="fw-semibold">{{ $catatan->anggotaPeriode?->santri?->nama ?? '—' }}</div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="text-muted small">Nomor induk</div>
                    <div class="fw-semibold">{{ $catatan->anggotaPeriode?->santri?->nomor_induk ?? '—' }}</div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="text-muted small">Kelompok Asal</div>
                    <div class="fw-semibold">
                        {{ $catatan->anggotaPeriode?->kelompok?->nama_kelompok ?? $catatan->anggotaPeriode?->kelompok?->nama ?? '—' }}
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="text-muted small">Periode SIK</div>
                    <div class="fw-semibold">{{ $catatan->anggotaPeriode?->periode?->nama_periode ?? '—' }}</div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="text-muted small">Kegiatan</div>
                    <div class="fw-semibold">{{ $kegiatan->nama_kegiatan }}</div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="text-muted small">Tanggal pelaksanaan</div>
                    <div class="fw-semibold">{{ $catatan->pertemuan?->tanggal?->format('d/m/Y') ?? '—' }}</div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="text-muted small">Status kehadiran pada pertemuan ini</div>
                    <div>
                        <span class="badge rounded-pill text-bg-{{ $catatan->status === 'Hadir' ? 'success' : ($catatan->status === 'Alpha' ? 'danger' : 'warning') }}">
                            {{ $catatan->status }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @if ($rekap)
            @php
                $statusRekap = $rekap->status_kehadiran;
            @endphp
            @foreach ([
                ['Total Hadir', $rekap->jumlah_hadir],
                ['Total Izin', $rekap->jumlah_izin],
                ['Total Sakit', $rekap->jumlah_sakit],
                ['Total Alpha', $rekap->jumlah_alpha],
                ['Total pertemuan Terlaksana', $rekap->total_pertemuan],
                ['Persentase kehadiran', $rekap->persentase_display],
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
            <div class="col-12">
                <div class="alert alert-light border mb-0">
                    Status pemenuhan batas kehadiran: <strong>{{ $statusRekap }}</strong>
                    (ambang {{ number_format($minimumPercentage, 1, ',', '.') }}%).
                </div>
            </div>
        @else
            <div class="col-12">
                <div class="alert alert-info mb-0">
                    Rekap kehadiran untuk santri dan kegiatan ini belum tersedia.
                </div>
            </div>
        @endif
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">Riwayat pertemuan</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Pertemuan ke</th>
                        <th>Status kehadiran</th>
                        <th>Status pertemuan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($riwayat as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item->pertemuan?->tanggal?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $item->pertemuan?->pertemuan_ke ?? '—' }}</td>
                            <td>{{ $item->status }}</td>
                            <td>{{ $item->pertemuan?->status ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                Belum ada riwayat pertemuan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
