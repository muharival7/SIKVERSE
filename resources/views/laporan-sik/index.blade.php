@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4">
    <div class="card border-0 shadow-sm mb-4" style="background:linear-gradient(135deg, #800000, #5b0000);border-radius:16px">
        <div class="card-body p-4 text-white">
        <h1 class="h3 fw-bold mb-2">Evaluasi Mutu Kelulusan</h1>
        <p class="mb-0 text-white-50">Perbandingan capaian kelulusan santri berdasarkan tahun periode SIK. Ambang kehadiran: {{ $minimumPercentageDisplay }} dari pertemuan Terlaksana.</p>
        </div>
    </div>

    <div class="row g-3 mb-4" id="laporan-kelulusan">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted">Peserta dengan Data Kelulusan</div>
                <div class="fs-3 fw-bold">{{ $totalPeserta }}</div>
            </div></div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted">Jumlah Lulus</div>
                <div class="fs-3 fw-bold">{{ $totalLulus }}</div>
            </div></div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted">Persentase Kelulusan</div>
                <div class="fs-3 fw-bold">{{ $persentaseKelulusanLabel }}</div>
            </div></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-1">Capaian kelulusan per tahun</h2>
            <p class="text-muted mb-4">Pilih tahun untuk membuka rincian evaluasi kelulusan santri.</p>
            <div class="d-flex flex-wrap gap-2 mb-4">
                @forelse ($kelulusanTahunan as $tahun)
                    <a class="btn btn-sm btn-outline-danger" href="{{ route('laporan-sik.tahun', $tahun['tahun']) }}">Tahun {{ $tahun['tahun'] }}</a>
                @empty
                    <span class="text-muted">Belum ada tahun yang dapat dipilih.</span>
                @endforelse
            </div>
            @forelse ($kelulusanTahunan as $tahun)
                @php
                    $total = max(1, (int) $tahun['total']);
                    $lulusWidth = ((int) $tahun['lulus'] / $total) * 100;
                    $tidakLulusWidth = ((int) $tahun['tidak_lulus'] / $total) * 100;
                    $belumWidth = ((int) $tahun['belum_ditetapkan'] / $total) * 100;
                @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <a class="fw-semibold text-decoration-none" href="{{ route('laporan-sik.tahun', $tahun['tahun']) }}">{{ $tahun['tahun'] }}</a>
                        <span class="text-muted">{{ $tahun['total'] }} peserta</span>
                    </div>
                    <div class="progress" style="height:14px" role="img"
                        aria-label="{{ $tahun['tahun'] }}: {{ $tahun['lulus'] }} lulus">
                        <div class="progress-bar bg-success" style="width: {{ $lulusWidth }}%"></div>
                        <div class="progress-bar bg-danger" style="width: {{ $tidakLulusWidth }}%"></div>
                        <div class="progress-bar bg-secondary" style="width: {{ $belumWidth }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0">Grafik akan tampil setelah data evaluasi kelulusan tersedia.</p>
            @endforelse
        </div>
    </div>

    <div class="card border-0 shadow-sm" id="perbandingan-tahun">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-1">Perbandingan Kelulusan Antar Tahun</h2>
            <p class="text-muted mb-3">Ringkasan status evaluasi kelulusan per tahun menggunakan satu sumber metrik kehadiran.</p>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-light"><tr><th>Tahun</th><th>Total Peserta</th><th>Lulus</th><th>Tidak Lulus</th><th>Belum Ditetapkan</th><th>Persentase Lulus</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($kelulusanTahunan as $tahun)
                            <tr>
                                <td>{{ $tahun['tahun'] }}</td>
                                <td>{{ $tahun['total'] }}</td>
                                <td>{{ $tahun['lulus'] }}</td>
                                <td>{{ $tahun['tidak_lulus'] }}</td>
                                <td>{{ $tahun['belum_ditetapkan'] }}</td>
                                <td>{{ $tahun['persentase_lulus_display'] }}</td>
                                <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="{{ route('laporan-sik.tahun', $tahun['tahun']) }}">Lihat Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data kelulusan tahunan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
