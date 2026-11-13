@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4">
    <div class="card border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,#12304a,#245778);border-radius:16px">
        <div class="card-body p-4 text-white">
        <h1 class="h3 fw-bold mb-2">Evaluasi Mutu Kelulusan</h1>
        <p class="mb-0 text-white-50">Perbandingan capaian kelulusan santri berdasarkan tahun periode SIK.</p>
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
                <div class="fs-3 fw-bold">{{ number_format($persentaseKelulusan, 2) }}%</div>
            </div></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-1">Capaian kelulusan per tahun</h2>
            <p class="text-muted mb-4">Proporsi status kelulusan dari peserta yang sudah dievaluasi.</p>
            @forelse ($kelulusanTahunan as $tahun)
                @php
                    $total = max(1, (int) $tahun->total_kelulusan);
                    $lulusWidth = ((int) $tahun->jumlah_lulus / $total) * 100;
                    $tidakLulusWidth = ((int) $tahun->jumlah_tidak_lulus / $total) * 100;
                    $belumWidth = ((int) $tahun->jumlah_belum_ditetapkan / $total) * 100;
                @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1"><strong>{{ $tahun->tahun }}</strong><span class="text-muted">{{ $tahun->total_kelulusan }} peserta</span></div>
                    <div class="progress" style="height:14px" role="img" aria-label="{{ $tahun->tahun }}: {{ $tahun->jumlah_lulus }} lulus, {{ $tahun->jumlah_tidak_lulus }} tidak lulus, {{ $tahun->jumlah_belum_ditetapkan }} belum ditetapkan">
                        <div class="progress-bar bg-success" style="width:{{ $lulusWidth }}%"></div>
                        <div class="progress-bar bg-danger" style="width:{{ $tidakLulusWidth }}%"></div>
                        <div class="progress-bar bg-secondary" style="width:{{ $belumWidth }}%"></div>
                    </div>
                    <div class="d-flex flex-wrap gap-3 small mt-1 text-muted"><span>Lulus {{ $tahun->jumlah_lulus }}</span><span>Tidak lulus {{ $tahun->jumlah_tidak_lulus }}</span><span>Belum ditetapkan {{ $tahun->jumlah_belum_ditetapkan }}</span></div>
                </div>
            @empty
                <p class="text-muted mb-0">Grafik akan tampil setelah data evaluasi kelulusan tersedia.</p>
            @endforelse
        </div>
    </div>

    <div class="card border-0 shadow-sm" id="perbandingan-tahun">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-1">Perbandingan Kelulusan Antar Tahun</h2>
            <p class="text-muted mb-3">Laporan ini hanya menampilkan ringkasan jumlah kelulusan per tahun.</p>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-light"><tr><th>Tahun</th><th>Total Peserta</th><th>Lulus</th><th>Tidak Lulus</th><th>Belum Ditetapkan</th><th>Persentase Lulus</th></tr></thead>
                    <tbody>
                        @forelse ($kelulusanTahunan as $tahun)
                            <tr>
                                <td>{{ $tahun->tahun }}</td>
                                <td>{{ $tahun->total_kelulusan }}</td>
                                <td>{{ $tahun->jumlah_lulus }}</td>
                                <td>{{ $tahun->jumlah_tidak_lulus }}</td>
                                <td>{{ $tahun->jumlah_belum_ditetapkan }}</td>
                                <td>{{ $tahun->total_kelulusan > 0 ? number_format(($tahun->jumlah_lulus / $tahun->total_kelulusan) * 100, 2) : '0.00' }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data kelulusan tahunan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
