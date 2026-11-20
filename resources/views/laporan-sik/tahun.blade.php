@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4">
    <div class="card border-0 shadow-sm mb-4" style="background:linear-gradient(135deg, #800000, #5b0000);border-radius:16px">
        <div class="card-body p-4 text-white">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <span class="text-uppercase small fw-bold" style="letter-spacing:.14em;color:rgba(255,255,255,.7)">Evaluasi Mutu Kelulusan</span>
                    <h1 class="h3 fw-bold mt-1 mb-2">Rincian Kelulusan Tahun {{ $tahun }}</h1>
                    <p class="mb-0 text-white-50">Metrik kehadiran dihitung dari pertemuan Terlaksana. Ambang kehadiran: {{ $minimumPercentageDisplay }}.</p>
                </div>
                <a class="btn btn-outline-light" href="{{ route('laporan-sik') }}">&larr; Kembali ke Laporan</a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted">Total Peserta</div>
                <div class="fs-3 fw-bold">{{ $ringkasan['total'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted">Lulus</div>
                <div class="fs-3 fw-bold text-success">{{ $ringkasan['lulus'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted">Tidak Lulus</div>
                <div class="fs-3 fw-bold text-danger">{{ $ringkasan['tidak_lulus'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted">Belum Ditetapkan</div>
                <div class="fs-3 fw-bold text-secondary">{{ $ringkasan['belum_ditetapkan'] }}</div>
            </div></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-6 col-md-3"><div class="small text-muted">Persentase Lulus</div><div class="h4 fw-bold mb-0">{{ $ringkasan['persentase_lulus_display'] }}</div></div>
                <div class="col-6 col-md-3"><div class="small text-muted">Memenuhi Ambang</div><div class="h4 fw-bold mb-0">{{ $ringkasan['memenuhi_ambang'] }}</div></div>
                <div class="col-6 col-md-3"><div class="small text-muted">Belum Memenuhi Ambang</div><div class="h4 fw-bold mb-0">{{ $ringkasan['belum_memenuhi_ambang'] }}</div></div>
                <div class="col-6 col-md-3"><div class="small text-muted">Tanpa Pertemuan Terlaksana</div><div class="h4 fw-bold mb-0">{{ $ringkasan['belum_ada_pertemuan_terlaksana'] }}</div></div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm" id="detail-evaluasi-kelulusan-tahun">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-1">Detail Evaluasi Kelulusan Santri {{ $tahun }}</h2>
            <p class="text-muted mb-3">Rincian status evaluasi, kehadiran, dan alasan evaluasi setiap santri pada tahun ini.</p>

            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Nama Santri</th>
                            <th>Nomor Induk</th>
                            <th>Periode</th>
                            <th>Kelompok</th>
                            <th>Terlaksana</th>
                            <th>Hadir</th>
                            <th>Izin</th>
                            <th>Sakit</th>
                            <th>Alpha</th>
                            <th>Persentase</th>
                            <th>Status Kehadiran</th>
                            <th>Status Evaluasi</th>
                            <th>Alasan Evaluasi</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($detailKelulusan as $index => $detail)
                            @php
                                $anggota = $detail->anggotaPeriodeSik;
                                $santri = $anggota?->santri;
                                $attendance = $detail->attendance;
                            @endphp

                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $santri?->nama ?? '-' }}</td>
                                <td>{{ $santri?->nomor_induk ?? '-' }}</td>
                                <td>{{ $anggota?->periode?->tahun ?? '-' }}</td>
                                <td>{{ $anggota?->kelompok?->nama_kelompok ?? '-' }}</td>
                                <td class="text-center">{{ $attendance['total_pertemuan'] }}</td>
                                <td class="text-center">{{ $attendance['jumlah_hadir'] }}</td>
                                <td class="text-center">{{ $attendance['jumlah_izin'] }}</td>
                                <td class="text-center">{{ $attendance['jumlah_sakit'] }}</td>
                                <td class="text-center">{{ $attendance['jumlah_alpha'] }}</td>
                                <td class="text-center">{{ $detail->persentase_kehadiran_display }}</td>
                                <td class="text-center">{{ $attendance['status'] }}</td>
                                <td><span class="badge {{ $detail->evaluasi_badge_class }}">{{ $detail->evaluasi_label }}</span></td>
                                <td>{{ $detail->alasan_kehadiran }}</td>
                                <td>{{ $detail->keterangan ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="text-center text-muted py-4">Belum ada data evaluasi kelulusan santri untuk tahun ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
