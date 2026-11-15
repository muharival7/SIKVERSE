@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Css/dashboard.css') }}?v={{ filemtime(public_path('Css/dashboard.css')) }}">
@endpush

@section('content')
<div class="container py-4 py-lg-5 sik-dashboard kalab-dashboard">
    <section class="sd-welcome sd-kalab-welcome" aria-labelledby="kalab-welcome-title">
        <div class="sd-welcome-copy">
            <span class="sd-eyebrow">SIKVERSE · MONITORING MUTU</span>
            <h1 id="kalab-welcome-title">Selamat datang, Ka-Lab Mutu</h1>
            <p>Di halaman ini Anda dapat melihat laporan jumlah mahasiswa yang lulus setiap tahun, membandingkan antartahun, serta memantau perubahan kelulusan sebagai bahan evaluasi mutu.</p>
        </div>
    </section>

    <section class="sd-kalab-summary-grid" aria-label="Ringkasan kelulusan">
        <article class="sd-card sd-kalab-stat sd-kalab-stat--green">
            <span class="sd-kalab-stat-icon"><x-sik-icon name="graduation" /></span>
            <div>
                <span class="sd-section-kicker">TAHUN {{ $graduationCurrentYear }}</span>
                <h2>Total Lulusan Tahun Ini</h2>
                <p><strong>{{ number_format($graduationCurrentSummary['lulus']) }}</strong> dari {{ number_format($graduationCurrentSummary['total']) }} mahasiswa</p>
            </div>
        </article>

        <article class="sd-card sd-kalab-stat sd-kalab-stat--rose">
            <span class="sd-kalab-stat-icon"><x-sik-icon name="report" /></span>
            <div>
                <span class="sd-section-kicker">EVALUASI TAHUNAN</span>
                <h2>Evaluasi Mutu Kelulusan</h2>
                <a href="{{ route('laporan-sik') }}#laporan-kelulusan">Lihat detail <x-sik-icon name="arrow-right" /></a>
            </div>
        </article>
    </section>

    <section class="sd-card sd-kalab-failures" aria-labelledby="kalab-failures-title">
        <div class="sd-card-heading">
            <div>
                <span class="sd-section-kicker">RINCIAN EVALUASI</span>
                <h2 id="kalab-failures-title">Santri dengan status Tidak Lulus</h2>
                <p class="sd-card-description">Alasan dihitung dari absensi tersimpan dan catatan evaluasi. Ambang: {{ $graduationMinimumPresent }} dari {{ $graduationMeetingCount }} pertemuan.</p>
            </div>
        </div>
        <div class="table-responsive sd-kalab-failures-table-wrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Santri</th>
                        <th scope="col">Periode</th>
                        <th scope="col" class="text-center">Kehadiran</th>
                        <th scope="col" class="text-center">Tidak hadir</th>
                        <th scope="col" class="text-center">Batas minimal</th>
                        <th scope="col">Alasan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($graduationFailures as $record)
                        <tr>
                            <td>
                                <strong>{{ $record->anggotaPeriodeSik?->santri?->nama ?? 'Data santri tidak tersedia' }}</strong>
                                <span class="d-block small text-muted">{{ $record->anggotaPeriodeSik?->santri?->nomor_induk ?? '—' }}</span>
                            </td>
                            <td>{{ $record->anggotaPeriodeSik?->periode?->nama_periode ?? '—' }}</td>
                            <td class="text-center text-nowrap">
                                <strong>{{ $record->jumlah_hadir_aktual }}/{{ $graduationMeetingCount }}</strong>
                                <span class="d-block small text-muted">{{ number_format($record->persentase_kehadiran_aktual, 1) }}%</span>
                            </td>
                            <td class="text-center">{{ $record->jumlah_tidak_hadir_aktual }}</td>
                            <td class="text-center text-nowrap">{{ $graduationMinimumPresent }} kali</td>
                            <td class="small sd-kalab-reason-cell">
                                {{ $record->alasan_ketidaklulusan }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada santri berstatus Tidak Lulus.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($graduationFailures->isNotEmpty())
            <p class="sd-kalab-failures-note mb-0">Menampilkan maksimal 10 evaluasi Tidak Lulus terbaru.</p>
        @endif
    </section>

    <section class="sd-kalab-chart-grid" aria-label="Grafik evaluasi kelulusan">
        <article class="sd-card sd-kalab-chart-card">
            <div class="sd-card-heading">
                <div>
                    <span class="sd-section-kicker">TREN KELULUSAN</span>
                    <h2>Jumlah Lulusan per Tahun</h2>
                </div>
                <span class="sd-kalab-chart-icon"><x-sik-icon name="chart" /></span>
            </div>
            <div class="sd-kalab-chart" data-graduation-chart="line" data-chart-id="graduation-count-data" role="img" aria-label="Grafik jumlah lulusan per tahun">
                <div class="sd-chart-empty" data-chart-empty @if ($graduationChartData->isNotEmpty()) hidden @endif>Belum ada data kelulusan per tahun.</div>
                <div class="sd-chart-canvas" data-chart-canvas></div>
            </div>
            <script type="application/json" id="graduation-count-data">@json($graduationChartData)</script>
        </article>

        <article class="sd-card sd-kalab-chart-card">
            <div class="sd-card-heading">
                <div>
                    <span class="sd-section-kicker">PERBANDINGAN STATUS</span>
                    <h2>Perbandingan Jumlah Kelulusan</h2>
                </div>
                <div class="sd-kalab-legend" aria-label="Legenda grafik">
                    <span><i class="is-pass" aria-hidden="true"></i>Lulus</span>
                    <span><i class="is-fail" aria-hidden="true"></i>Tidak lulus</span>
                </div>
            </div>
            <div class="sd-kalab-chart" data-graduation-chart="comparison" data-chart-id="graduation-comparison-data" role="img" aria-label="Grafik perbandingan jumlah peserta lulus dan tidak lulus tiap tahun">
                <div class="sd-chart-empty" data-chart-empty @if ($graduationChartData->isNotEmpty()) hidden @endif>Belum ada data kelulusan per tahun.</div>
                <div class="sd-chart-canvas" data-chart-canvas></div>
            </div>
            <script type="application/json" id="graduation-comparison-data">@json($graduationChartData)</script>
        </article>
    </section>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/graduation-charts.js') }}?v={{ filemtime(public_path('js/graduation-charts.js')) }}" defer></script>
@endpush
