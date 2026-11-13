@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Css/dashboard.css') }}?v={{ filemtime(public_path('Css/dashboard.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/dashboard-charts.js') }}" defer></script>
@endpush

@section('content')
<div class="container py-4 py-lg-5 sik-dashboard admin-dashboard">
    <section class="sd-welcome" aria-labelledby="admin-welcome-title">
        <div class="sd-welcome-copy">
            <span class="sd-eyebrow">SIKVERSE · PUSAT ADMINISTRASI</span>
            <h1 id="admin-welcome-title">Selamat Datang</h1>
            <p>Kelola Data dan Informasi Sekolah Islam Kebangsaan</p>
        </div>
        <div class="sd-profile-summary" aria-label="Informasi periode">
            <span class="sd-profile-chip">
                <span class="sd-chip-label">Periode aktif</span>
                <strong>{{ $dashboardPeriod?->nama_periode ?? 'Belum ada periode aktif' }}</strong>
            </span>
            <span class="sd-profile-chip">
                <span class="sd-chip-label">Ambang kelulusan</span>
                <strong>{{ config('sik.attendance.minimum_present', 14) }} dari {{ config('sik.attendance.meeting_count', 16) }} pertemuan</strong>
            </span>
            <span class="sd-profile-chip">
                <span class="sd-chip-label">Tanggal</span>
                <strong>{{ now()->locale('id')->translatedFormat('d F Y') }}</strong>
            </span>
        </div>
    </section>

    <section class="row g-3 mt-2" aria-label="Ringkasan SIK">
        <div class="col-6 col-xl-3">
            <article class="sd-kpi-card">
                <div class="sd-kpi-heading"><span>Santri aktif</span><span class="sd-kpi-icon maroon"><x-sik-icon name="students" /></span></div>
                <strong>{{ number_format($adminStats['totalSantri']) }}</strong>
                <small>Terdaftar di sistem SIKVERSE</small>
            </article>
        </div>
        <div class="col-6 col-xl-3">
            <article class="sd-kpi-card">
                <div class="sd-kpi-heading"><span>Total dosen</span><span class="sd-kpi-icon green"><x-sik-icon name="users" /></span></div>
                <strong>{{ number_format($adminStats['totalDosen']) }}</strong>
                <small>Dosen aktif SIK</small>
            </article>
        </div>
        <div class="col-6 col-xl-3">
            <article class="sd-kpi-card">
                <div class="sd-kpi-heading"><span>Rata-rata kehadiran</span><span class="sd-kpi-icon blue"><x-sik-icon name="report" /></span></div>
                <strong>{{ $adminStats['rataKehadiran'] === null ? '—' : number_format($adminStats['rataKehadiran'], 0) . '%' }}</strong>
                <small>{{ $adminStats['totalRekap'] }} rekap kehadiran</small>
            </article>
        </div>
        <div class="col-6 col-xl-3">
            <article class="sd-kpi-card">
                <div class="sd-kpi-heading"><span>Mahasiswa lulus</span><span class="sd-kpi-icon gold"><x-sik-icon name="check" /></span></div>
                <strong>{{ number_format($adminStats['mahasiswaLulus']) }}</strong>
                <small>{{ $dashboardPeriod?->nama_periode ?? 'Seluruh periode' }}</small>
            </article>
        </div>
    </section>

    <div class="row g-4 mt-1">
        <section class="col-12 col-xl-7" aria-labelledby="admin-trend-title">
            <div class="sd-card sd-trend-card h-100">
                <div class="sd-card-heading">
                    <div>
                        <span class="sd-section-kicker">MONITORING</span>
                        <h2 id="admin-trend-title">Tren kehadiran</h2>
                        <p class="sd-card-description">Persentase hadir per bulan pada {{ $dashboardPeriod?->nama_periode ?? 'seluruh periode' }}.</p>
                    </div>
                    <span class="sd-trend-badge"><i aria-hidden="true"></i> 6 bulan</span>
                </div>

                <div class="sd-chart" data-attendance-chart data-trend-id="admin-attendance-trend-data" role="img" aria-label="Grafik tren kehadiran santri dalam enam bulan terakhir">
                    <div class="sd-chart-empty" data-chart-empty @if ($adminAttendanceTrend->isNotEmpty()) hidden @endif>
                        Tren akan tampil setelah absensi periode ini tercatat.
                    </div>
                    <div class="sd-chart-canvas" data-chart-canvas></div>
                </div>
                <script type="application/json" id="admin-attendance-trend-data">@json($adminAttendanceTrend)</script>
                <p class="sd-chart-note">Rasio hadir dihitung dari catatan absensi yang tersedia setiap bulan.</p>
            </div>
        </section>

        <section class="col-12 col-xl-5" aria-labelledby="admin-attendance-share-title">
            <div class="sd-card h-100">
                <div class="sd-card-heading">
                    <div>
                        <span class="sd-section-kicker">RINGKASAN KEHADIRAN</span>
                        <h2 id="admin-attendance-share-title">Persentase kehadiran</h2>
                        <p class="sd-card-description">Perbandingan hadir dan belum hadir pada seluruh rekap periode aktif.</p>
                    </div>
                </div>
                <div class="sd-attendance-overview mb-0">
                    <div class="sd-attendance-ring-wrap">
                        <div class="sd-attendance-ring" role="img" aria-label="Persentase hadir {{ $adminAttendanceDistribution['persentase'] === null ? 'belum tersedia' : number_format($adminAttendanceDistribution['persentase'], 1).' persen' }}" style="--attendance-value: {{ $adminAttendanceDistribution['persentase'] ?? 0 }}%;">
                            <div class="sd-ring-center">
                                <strong>{{ $adminAttendanceDistribution['persentase'] === null ? '—' : number_format($adminAttendanceDistribution['persentase'], 0).'%' }}</strong>
                                <span>Hadir</span>
                            </div>
                        </div>
                        <span class="sd-ring-caption">{{ $adminStats['totalRekap'] }} rekap santri</span>
                    </div>
                    <div class="sd-status-summary" aria-label="Jumlah kehadiran">
                        <div class="sd-status-item"><span class="sd-status-name"><i class="sd-status-dot present" aria-hidden="true"></i>Hadir</span><strong>{{ number_format($adminAttendanceDistribution['hadir']) }}</strong></div>
                        <div class="sd-status-item"><span class="sd-status-name"><i class="sd-status-dot absent" aria-hidden="true"></i>Belum hadir</span><strong>{{ number_format($adminAttendanceDistribution['tidakHadir']) }}</strong></div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="row g-4 mt-1">
        <section class="col-12" aria-labelledby="admin-recap-title">
            <div class="sd-card h-100">
                <div class="sd-recent-heading admin-recap-heading">
                    <div>
                        <span class="sd-section-kicker">DATA TERBARU</span>
                        <h2 id="admin-recap-title">Rekap kehadiran terbaru</h2>
                        <p>Absensi santri dari {{ $dashboardPeriod?->nama_periode ?? 'periode terbaru' }}.</p>
                    </div>
                    <a class="sd-button sd-button-outline" href="{{ route('rekap-kehadiran-sik.index') }}">Lihat rekap</a>
                </div>

                <div class="table-responsive sd-table-wrap">
                    <table class="sd-table">
                        <thead>
                            <tr>
                                <th scope="col">Santri</th>
                                <th scope="col">Kegiatan</th>
                                <th scope="col">Tanggal</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($adminRecentAttendance as $attendance)
                                @php
                                    $statusClass = match ($attendance->status) {
                                        'Hadir' => 'present',
                                        'Izin' => 'permission',
                                        'Sakit' => 'sick',
                                        'Alpha' => 'absent',
                                        default => 'incomplete',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $attendance->anggotaPeriode?->santri?->nama ?? 'Santri' }}</strong>
                                        <span class="sd-row-subtitle">{{ $attendance->anggotaPeriode?->santri?->nomor_induk ?? '—' }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ $attendance->pertemuan?->kegiatan?->nama_kegiatan ?? 'Kegiatan SIK' }}</strong>
                                        <span class="sd-row-subtitle">{{ $attendance->anggotaPeriode?->kelompok?->nama_kelompok ?? 'Kelompok' }} · pertemuan {{ $attendance->pertemuan?->pertemuan_ke ?? '—' }}</span>
                                    </td>
                                    <td>{{ $attendance->pertemuan?->tanggal?->format('d/m/Y') ?? '—' }}</td>
                                    <td><span class="sd-status-pill {{ $statusClass }}">{{ $attendance->status }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="sd-empty-cell">
                                        <span class="sd-empty-icon"><x-sik-icon name="clock" /></span>
                                        Belum ada absensi pada periode yang ditampilkan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <div class="row g-4 mt-1">
        <section class="col-12" aria-labelledby="admin-certificate-panel-title">
            <div class="sd-card admin-certificate-panel">
                <div class="sd-recent-heading">
                    <div>
                        <span class="sd-section-kicker">PENGELOLAAN SERTIFIKAT</span>
                        <h2 id="admin-certificate-panel-title">Pengelolaan Sertifikat</h2>
                        <p class="sd-card-description">Kelola dan lihat sertifikat kelulusan yang sudah tercatat.</p>
                    </div>
                    <a class="sd-button sd-button-outline" href="{{ route('sertifikat-sik.index') }}">Lihat</a>
                </div>
            </div>
        </section>
    </div>

</div>
@endsection
