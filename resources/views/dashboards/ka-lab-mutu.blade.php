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
                <p><strong>{{ number_format($graduationCurrentSummary['lulus']) }}</strong> dari {{ number_format($graduationCurrentSummary['total']) }} mahasiswa · {{ number_format($graduationCurrentSummary['belum_ditetapkan']) }} belum ditetapkan</p>
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
