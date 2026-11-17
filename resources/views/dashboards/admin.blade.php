@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Css/dashboard.css') }}?v={{ filemtime(public_path('Css/dashboard.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/dashboard-charts.js') }}" defer></script>
@endpush

@section('content')
<div class="container-fluid py-4 px-4 px-xl-5 sik-dashboard admin-dashboard">
    <section class="sd-welcome admin-dashboard-welcome mb-3" aria-labelledby="admin-welcome-title">
        <div class="sd-welcome-copy">
            <span class="sd-eyebrow">SIKVERSE · PUSAT ADMINISTRASI</span>
            <h1 id="admin-welcome-title">Selamat Datang</h1>
            <p>Kelola data dan informasi Sekolah Islam Kebangsaan.</p>
        </div>
    </section>

    <section class="row g-3" aria-label="Ringkasan SIK">
        <div class="col-6 col-xl-3"><article class="sd-kpi-card"><div class="sd-kpi-heading"><span>Total Santri</span><span class="sd-kpi-icon maroon"><x-sik-icon name="students" /></span></div><strong>{{ number_format($adminStats['totalSantri']) }}</strong><small>Data santri terdaftar</small></article></div>
        <div class="col-6 col-xl-3"><article class="sd-kpi-card"><div class="sd-kpi-heading"><span>Total Dosen</span><span class="sd-kpi-icon gold"><x-sik-icon name="users" /></span></div><strong>{{ number_format($adminStats['totalDosen']) }}</strong><small>Data dosen terdaftar</small></article></div>
        <div class="col-6 col-xl-3"><article class="sd-kpi-card"><div class="sd-kpi-heading"><span>Rata-rata Kehadiran</span><span class="sd-kpi-icon blue"><x-sik-icon name="calendar" /></span></div><strong>{{ $adminStats['rataKehadiran'] === null ? '—' : number_format($adminStats['rataKehadiran'], 0).'%' }}</strong><small>{{ number_format($adminStats['totalRekap']) }} rekap pada periode aktif</small></article></div>
        <div class="col-6 col-xl-3"><article class="sd-kpi-card"><div class="sd-kpi-heading"><span>Santri Lulus</span><span class="sd-kpi-icon green"><x-sik-icon name="check" /></span></div><strong>{{ number_format($adminStats['mahasiswaLulus']) }}</strong><small>{{ $dashboardPeriod?->nama_periode ?? 'Periode aktif' }}</small></article></div>
    </section>

    <div class="row g-3 mt-1">
        <section class="col-12 col-xl-6" aria-labelledby="admin-attendance-share-title">
            <div class="sd-card h-100">
                <div class="sd-card-heading"><div><span class="sd-section-kicker">REKAP KEHADIRAN SANTRI</span><h2 id="admin-attendance-share-title">Persentase Kehadiran</h2><p class="sd-card-description">{{ $dashboardPeriod?->nama_periode ?? 'Seluruh periode' }}</p></div></div>
                <div class="sd-attendance-overview mb-0">
                    <div class="sd-attendance-ring-wrap">
                        <div class="sd-attendance-ring" role="img" aria-label="Persentase hadir {{ $adminAttendanceDistribution['persentase'] === null ? 'belum tersedia' : number_format($adminAttendanceDistribution['persentase'], 1).' persen' }}" style="--attendance-value: {{ $adminAttendanceDistribution['persentase'] ?? 0 }}%;"><div class="sd-ring-center"><strong>{{ $adminAttendanceDistribution['persentase'] === null ? '—' : number_format($adminAttendanceDistribution['persentase'], 0).'%' }}</strong><span>Kehadiran</span></div></div>
                        <span class="sd-ring-caption">{{ number_format($adminStats['totalRekap']) }} rekap</span>
                    </div>
                    <div class="sd-status-summary" aria-label="Ringkasan kehadiran">
                        <div class="sd-status-item"><span class="sd-status-name"><i class="sd-status-dot present" aria-hidden="true"></i>Hadir</span><strong>{{ number_format($adminAttendanceDistribution['hadir']) }}</strong></div>
                        <div class="sd-status-item"><span class="sd-status-name"><i class="sd-status-dot absent" aria-hidden="true"></i>Belum hadir</span><strong>{{ number_format($adminAttendanceDistribution['tidakHadir']) }}</strong></div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-3"><a class="sd-text-link" href="{{ route('rekap-kehadiran-sik.index') }}">Buka rekap absensi <x-sik-icon name="arrow-right" /></a></div>
            </div>
        </section>

        <section class="col-12 col-xl-6" aria-labelledby="admin-trend-title">
            <div class="sd-card h-100">
                <div class="sd-card-heading"><div><span class="sd-section-kicker">TREN KEHADIRAN</span><h2 id="admin-trend-title">Kehadiran per Bulan</h2><p class="sd-card-description">{{ $dashboardPeriod?->nama_periode ?? 'Seluruh periode' }}</p></div></div>
                <div class="sd-chart" data-attendance-chart data-trend-id="admin-attendance-trend-data" role="img" aria-label="Grafik tren kehadiran santri">
                    <div class="sd-chart-empty" data-chart-empty @if ($adminAttendanceTrend->isNotEmpty()) hidden @endif>Grafik akan tampil setelah absensi dicatat.</div>
                    <div class="sd-chart-canvas" data-chart-canvas></div>
                </div>
                <script type="application/json" id="admin-attendance-trend-data">@json($adminAttendanceTrend)</script>
            </div>
        </section>
    </div>

    <div class="row g-3 mt-1">
        <section class="col-12 col-xl-7" aria-labelledby="admin-recent-students-title">
            <div class="sd-card h-100 p-0 overflow-hidden">
                <div class="sd-recent-heading px-4 pt-4">
                    <div><span class="sd-section-kicker">DATA TERBARU</span><h2 id="admin-recent-students-title">Data Santri Terbaru</h2></div>
                    <a class="sd-button sd-button-outline" href="{{ route('santris.index') }}">Lihat Semua <x-sik-icon name="arrow-right" /></a>
                </div>
                <div class="table-responsive sd-table-wrap">
                    <table class="sd-table">
                        <thead><tr><th scope="col">No</th><th scope="col">NIM</th><th scope="col">Nama Santri</th><th scope="col">Program Studi</th><th scope="col">Status</th></tr></thead>
                        <tbody>
                            @forelse ($adminRecentSantris as $santri)
                                <tr><td>{{ $loop->iteration }}</td><td>{{ $santri->nomor_induk }}</td><td><strong>{{ $santri->nama }}</strong></td><td>{{ $santri->program_studi ?: '—' }}</td><td><span class="sd-status-pill {{ $santri->status === 'Aktif' ? 'present' : 'absent' }}">{{ $santri->status }}</span></td></tr>
                            @empty
                                <tr><td colspan="5" class="sd-empty-cell">Belum ada data santri. Gunakan menu Data Santri untuk menambah data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="col-12 col-xl-5" aria-labelledby="admin-certificate-panel-title">
            <div class="sd-card h-100 admin-certificate-panel">
                <div class="admin-certificate-heading">
                    <span class="sd-kpi-icon gold"><x-sik-icon name="certificate" /></span>
                    <div>
                        <span class="sd-section-kicker">PENGELOLAAN SERTIFIKAT</span>
                        <h2 id="admin-certificate-panel-title" class="admin-certificate-title">Sertifikat Kelulusan</h2>
                    </div>
                </div>

                <p class="admin-certificate-description">Kelola sertifikat santri yang sudah dinyatakan lulus dan memenuhi ambang kehadiran.</p>

                <div class="admin-certificate-callout">
                    <div class="admin-certificate-count">
                        <strong class="admin-certificate-count-value">{{ number_format($adminStats['mahasiswaLulus']) }}</strong>
                        <span class="admin-certificate-count-label">santri memenuhi syarat kelulusan</span>
                    </div>
                    <p class="admin-certificate-note">Data kelulusan berasal dari hasil evaluasi dan kehadiran tersimpan.</p>
                </div>

                <a class="sd-button sd-button-primary admin-certificate-action" href="{{ route('sertifikat-sik.index') }}">
                    Buka Pengelolaan Sertifikat
                    <x-sik-icon name="arrow-right" />
                </a>
            </div>
        </section>
    </div>
</div>
@endsection
