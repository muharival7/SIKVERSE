@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Css/dashboard.css') }}?v={{ filemtime(public_path('Css/dashboard.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/dashboard-charts.js') }}" defer></script>
@endpush

@section('content')
<div class="container-fluid py-4 px-4 sik-dashboard instructor-dashboard">
    <section class="sd-welcome" aria-labelledby="instructor-welcome-title">
        <div class="sd-welcome-copy">
            <span class="sd-eyebrow">SIKVERSE · INSTRUKTUR SIK</span>
            <h1 id="instructor-welcome-title">Selamat datang, {{ $user->name }}</h1>
            <p>Pantau kelompok, progres peserta, dan tren kehadiran dalam program yang kamu ampu.</p>
        </div>
     </section>

    <section class="row g-3 mt-2" aria-label="Ringkasan instruktur">
        <div class="col-12 col-sm-4">
            <article class="sd-kpi-card">
                <div class="sd-kpi-heading"><span>Kelompok yang diampu</span><span class="sd-kpi-icon maroon"><x-sik-icon name="home" /></span></div>
                <strong>{{ number_format($infrastrukturStats['totalKelompok']) }}</strong>
                <small>Kelompok terdaftar pada akun ini</small>
            </article>
        </div>
        <div class="col-12 col-sm-4">
            <article class="sd-kpi-card">
                <div class="sd-kpi-heading"><span>Peserta aktif</span><span class="sd-kpi-icon green"><x-sik-icon name="target" /></span></div>
                <strong>{{ number_format($infrastrukturStats['pesertaAktif']) }}</strong>
                <small>Terdaftar di kelompok yang diampu</small>
            </article>
        </div>
        <div class="col-12 col-sm-4">
            <article class="sd-kpi-card">
                <div class="sd-kpi-heading"><span>Rata-rata kehadiran</span><span class="sd-kpi-icon blue"><x-sik-icon name="report" /></span></div>
                <strong>{{ $infrastrukturStats['rataKehadiran'] === null ? '—' : number_format($infrastrukturStats['rataKehadiran'], 0) . '%' }}</strong>
                <small>{{ $infrastrukturStats['totalRekap'] }} rekap kegiatan</small>
            </article>
        </div>
    </section>

    <div class="row g-4 mt-1">
        <section class="col-12 col-xl-7" aria-labelledby="instructor-trend-title">
            <div class="sd-card sd-trend-card h-100">
                <div class="sd-card-heading">
                    <div>
                        <span class="sd-section-kicker">MONITORING KELOMPOK</span>
                        <h2 id="instructor-trend-title">Tren kehadiran</h2>
                        <p class="sd-card-description">Rasio hadir bulanan dari catatan kelompok yang kamu ampu.</p>
                    </div>
                    <span class="sd-trend-badge"><i aria-hidden="true"></i> 6 bulan</span>
                </div>
                <div class="sd-chart" data-attendance-chart data-trend-id="instructor-attendance-trend-data" role="img" aria-label="Grafik tren kehadiran kelompok dalam enam bulan terakhir">
                    <div class="sd-chart-empty" data-chart-empty @if ($infrastrukturAttendanceTrend->isNotEmpty()) hidden @endif>
                        Grafik akan tampil setelah absensi kelompok tercatat.
                    </div>
                    <div class="sd-chart-canvas" data-chart-canvas></div>
                </div>
                <script type="application/json" id="instructor-attendance-trend-data">@json($infrastrukturAttendanceTrend)</script>
                <div class="sd-instructor-card-footer">
                    <span>Grafik memakai status absensi yang tercatat pada pertemuan.</span>
                    <a class="sd-button sd-button-outline" href="{{ route('rekap-kehadiran-sik.index') }}">Lihat rekap lengkap</a>
                </div>
            </div>
        </section>

        <section class="col-12 col-xl-5" aria-labelledby="instructor-recent-title">
            <div class="sd-card h-100">
                <div class="sd-recent-heading admin-recap-heading">
                    <div>
                        <span class="sd-section-kicker">ABSENSI TERBARU</span>
                        <h2 id="instructor-recent-title">Catatan terbaru</h2>
                        <p>Kehadiran peserta di kelompok yang kamu ampu.</p>
                    </div>
                </div>
                <div class="sd-recent-list">
                    @forelse ($infrastrukturRecentAttendance as $attendance)
                        @php
                            $statusClass = match ($attendance->status) {
                                'Hadir' => 'present',
                                'Izin' => 'permission',
                                'Sakit' => 'sick',
                                'Alpha' => 'absent',
                                default => 'incomplete',
                            };
                        @endphp
                        <div class="sd-recent-row">
                            <div class="sd-recent-avatar" aria-hidden="true">{{ mb_substr($attendance->anggotaPeriode?->santri?->nama ?? 'S', 0, 1) }}</div>
                            <div class="sd-recent-row-copy">
                                <strong>{{ $attendance->anggotaPeriode?->santri?->nama ?? 'Santri' }}</strong>
                                <span>{{ $attendance->pertemuan?->kegiatan?->nama_kegiatan ?? 'Kegiatan SIK' }} · {{ $attendance->pertemuan?->tanggal?->format('d/m/Y') ?? '—' }}</span>
                            </div>
                            <span class="sd-status-pill {{ $statusClass }}">{{ $attendance->status }}</span>
                        </div>
                    @empty
                        <div class="sd-recent-empty">Belum ada absensi terbaru untuk kelompokmu.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    <section class="mt-5" aria-labelledby="instructor-menu-title">
        <div class="sd-section-heading">
            <div>
                <span class="sd-section-kicker">AKSES CEPAT</span>
                <h2 id="instructor-menu-title">Menu instruktur</h2>
            </div>
            <p>Kelola kegiatan pembelajaran dan layanan peserta.</p>
        </div>

        <div class="row g-3 mt-1">
            @php $cards = [
                ['Nilai Santri','Input dan perbarui nilai santri di kelompok yang Anda ampu.','nilai.index','book','primary'],
                ['Kelompok Saya','Lihat daftar kelompok yang menjadi tanggung jawab instruktur.','instruktur.kelompok.index','home','info'],
                ['Data Anggota','Lihat santri yang tergabung dalam kelompok.','instruktur.anggota.index','students','success'],
                ['Absensi Santri','Pantau fingerprint dan pencatatan kehadiran manual.','absensi-sik.index','attendance','danger'],
            ]; @endphp
            @foreach ($cards as $card)
                <div class="col-12 col-sm-6 col-xl-4">
                    <a href="{{ route($card[2]) }}" class="text-decoration-none">
                        <article class="sd-menu-card h-100">
                            <div class="d-flex align-items-start justify-content-between">
                                <span class="sd-menu-icon {{ $card[4] }}"><x-sik-icon :name="$card[3]" /></span>
                                <span class="sd-menu-arrow"><x-sik-icon name="arrow-up-right" /></span>
                            </div>
                            <h3>{{ $card[0] }}</h3>
                            <p>{{ $card[1] }}</p>
                        </article>
                    </a>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
