@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Css/dashboard.css') }}?v={{ filemtime(public_path('Css/dashboard.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/dashboard-charts.js') }}" defer></script>
@endpush

@section('content')
<div class="container py-4 py-lg-5 sik-dashboard sd-santri-dashboard">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    <section class="sd-welcome" aria-labelledby="sd-welcome-title">
        <div class="sd-welcome-copy">
            <span class="sd-eyebrow">SIKVERSE · SEKOLAH ISLAM KEBANGSAAN</span>
            <h1 id="sd-welcome-title">Selamat datang, {{ $santri?->nama ?? $user->name }}</h1>
            <p>Semoga hari ini menjadi langkah baik untuk ke depannya. Pantau kehadiran dan progres SIK kamu dari satu tempat.</p>
        </div>

        <div class="sd-profile-summary" aria-label="Informasi peserta">
            <span class="sd-profile-chip">
                <span class="sd-chip-label">Nomor induk</span>
                <strong>{{ $santri?->nomor_induk ?? 'Belum tersedia' }}</strong>
            </span>
            <span class="sd-profile-chip">
                <span class="sd-chip-label">Periode aktif</span>
                <strong>{{ $keanggotaan?->periode?->nama_periode ?? 'Belum terdaftar' }}</strong>
            </span>
            <span class="sd-profile-chip">
                <span class="sd-chip-label">Kelompok</span>
                <strong>{{ $keanggotaan?->kelompok?->nama_kelompok ?? 'Belum ditentukan' }}</strong>
            </span>
        </div>
    </section>

    <div class="row g-4 mt-1">
        <section class="col-12 col-xl-7" aria-labelledby="sd-attendance-title">
            <div class="sd-card sd-attendance-card h-100">
                <div class="sd-card-heading">
                    <div>
                        <span class="sd-section-kicker">KEHADIRAN SIK</span>
                        <h2 id="sd-attendance-title">Riwayat dan persentase kehadiran</h2>
                    </div>
                    <a class="sd-text-link" href="{{ route('absensi-saya') }}">Lihat rekap <x-sik-icon name="arrow-up-right" /></a>
                </div>

                <div class="sd-attendance-overview">
                    <div class="sd-attendance-ring-wrap">
                        <div class="sd-attendance-ring" role="img" aria-label="Persentase kehadiran {{ $attendancePercentageDisplay === '—' ? $graduationEvaluation['metric']['status'] : $attendancePercentageDisplay }}" style="--attendance-value: {{ $attendancePercentage ?? 0 }}%;">
                            <div class="sd-ring-center">
                                <strong>{{ $attendancePercentageDisplay }}</strong>
                                <span>{{ $attendancePercentageDisplay === '—' ? $graduationEvaluation['metric']['status'] : 'Kehadiran' }}</span>
                            </div>
                        </div>
                        <span class="sd-ring-caption">Ambang {{ $minimumPercentageDisplay }} dari pertemuan Terlaksana</span>
                    </div>

                    <div class="sd-status-summary" aria-label="Rincian status absensi">
                        @foreach ([
                            ['label' => 'Hadir', 'value' => $attendanceCounts['Hadir'], 'class' => 'present'],
                            ['label' => 'Izin', 'value' => $attendanceCounts['Izin'], 'class' => 'permission'],
                            ['label' => 'Sakit', 'value' => $attendanceCounts['Sakit'], 'class' => 'sick'],
                            ['label' => 'Alpha', 'value' => $attendanceCounts['Alpha'], 'class' => 'absent'],
                        ] as $status)
                            <div class="sd-status-item">
                                <span class="sd-status-name"><i class="sd-status-dot {{ $status['class'] }}" aria-hidden="true"></i>{{ $status['label'] }}</span>
                                <strong>{{ $status['value'] }}</strong>
                            </div>
                        @endforeach
                        @if ($attendanceCounts['Tidak Lengkap'] > 0)
                            <div class="sd-status-item">
                                <span class="sd-status-name"><i class="sd-status-dot incomplete" aria-hidden="true"></i>Tidak lengkap</span>
                                <strong>{{ $attendanceCounts['Tidak Lengkap'] }}</strong>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="sd-recent-heading">
                    <div>
                        <h3>Rekap kehadiran terbaru</h3>
                        <p>{{ $attendanceRecordCount }} catatan pada periode yang dipilih</p>
                    </div>
                </div>

                <div class="table-responsive sd-table-wrap">
                    <table class="sd-table">
                        <thead>
                            <tr>
                                <th scope="col">Tanggal</th>
                                <th scope="col">Kegiatan / pertemuan</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentAttendance as $attendance)
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
                                    <td>{{ $attendance->pertemuan?->tanggal?->format('d/m/Y') ?? $attendance->created_at?->format('d/m/Y') ?? '—' }}</td>
                                    <td>
                                        <strong>{{ $attendance->pertemuan?->kegiatan?->nama_kegiatan ?? 'Kegiatan SIK' }}</strong>
                                        <span class="sd-row-subtitle">Pertemuan {{ $attendance->pertemuan?->pertemuan_ke ?? '—' }}</span>
                                    </td>
                                    <td><span class="sd-status-pill {{ $statusClass }}">{{ $attendance->status }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="sd-empty-cell">
                                        <span class="sd-empty-icon"><x-sik-icon name="clock" /></span>
                                        Belum ada riwayat absensi untuk ditampilkan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($activityRecaps->isNotEmpty())
                    <div class="sd-activity-recaps">
                        <h3>Ringkasan per kegiatan</h3>
                        <div class="sd-activity-list">
                            @foreach ($activityRecaps as $recap)
                                <div class="sd-activity-row">
                                    <div class="sd-activity-copy">
                                        <strong>{{ $recap['nama_kegiatan'] }}</strong>
                                        <span>
                                            {{ $recap['status'] === $noMeetingsStatus
                                                ? $noMeetingsStatus
                                                : $recap['jumlah_hadir'].' Hadir · '.$recap['jumlah_izin'].' Izin · '.$recap['jumlah_sakit'].' Sakit · '.$recap['jumlah_alpha'].' Alpha dari '.$recap['total_pertemuan'].' Terlaksana' }}
                                        </span>
                                        <em class="sd-recap-state {{ $recap['memenuhi'] ? 'success' : '' }}">
                                            {{ $recap['persentase_display'] }}
                                        </em>
                                    </div>
                                    <div class="sd-activity-progress" role="img" aria-label="{{ $recap['persentase_display'] }} kehadiran">
                                        <span style="width: {{ $recap['persentase'] ?? 0 }}%"></span>
                                    </div>
                                    <strong class="sd-activity-percent">{{ $recap['persentase_display'] }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="sd-card-footer">
                    <span>Persentase dihitung dari jumlah Hadir dibanding pertemuan Terlaksana per kegiatan.</span>
                </div>
            </div>
        </section>

        <div class="col-12 col-xl-5">
            <section class="sd-card sd-graduation-card mb-4" aria-labelledby="sd-graduation-title">
                <div class="sd-card-heading">
                    <div>
                        <span class="sd-section-kicker">PROGRES AKADEMIK</span>
                        <h2 id="sd-graduation-title">Status kelulusan</h2>
                    </div>
                    <span class="sd-graduation-icon"><x-sik-icon name="check" /></span>
                </div>

                @php
                    $graduationClass = match ($graduationEvaluation['code']) {
                        \App\Services\AttendanceRecapService::GRADUATION_PASSED => 'success',
                        \App\Services\AttendanceRecapService::GRADUATION_FAILED => 'danger',
                        default => 'pending',
                    };
                @endphp
                <div class="sd-graduation-status {{ $graduationClass }}">
                    <strong>{{ $graduationEvaluation['label'] }}</strong>
                    <span>{{ $graduationEvaluation['alasan'] }}</span>
                </div>
                @if ($kelulusan?->tanggal_kelulusan)
                    <p class="sd-graduation-date">Catatan tersimpan: {{ $kelulusan->status }} · {{ $kelulusan->tanggal_kelulusan->locale('id')->translatedFormat('d F Y') }}</p>
                @endif
            </section>

            <section class="sd-card sd-certificate-card" aria-labelledby="sd-certificate-title">
                <div class="sd-certificate-icon"><x-sik-icon name="certificate" /></div>
                <div class="sd-certificate-copy">
                    <span class="sd-section-kicker">SERTIFIKAT SIK</span>
                    <h2 id="sd-certificate-title">{{ $sertifikat ? 'Sertifikat kamu tersedia' : 'Sertifikat SIK' }}</h2>
                    <p>
                        @if ($sertifikat)
                            Nomor {{ $sertifikat->nomor_sertifikat }} · diterbitkan {{ $sertifikat->tanggal_terbit?->locale('id')->translatedFormat('d F Y') ?? '—' }}.
                        @else
                            Sertifikat akan muncul di sini setelah diterbitkan.
                        @endif
                    </p>
                    @if ($sertifikat && $graduationEvaluation['code'] !== \App\Services\AttendanceRecapService::GRADUATION_PASSED)
                        <p class="small text-muted mb-0">Berkas lama tetap tersedia; evaluasi kehadiran terbaru: {{ $graduationEvaluation['label'] }}.</p>
                    @endif
                </div>
                @if ($sertifikat)
                    <a class="sd-button sd-button-green" href="{{ route('sertifikat-sik.download', $sertifikat) }}">Unduh sertifikat <x-sik-icon name="download" /></a>
                @else
                    <a class="sd-button sd-button-outline" href="{{ route('sertifikat-saya') }}">Lihat sertifikat</a>
                @endif
            </section>
        </div>
    </div>

    <section class="sd-card sd-trend-card sd-trend-card--compact mt-4" aria-labelledby="sd-trend-title">
        <div class="sd-card-heading">
            <div>
                <span class="sd-section-kicker">ANALISIS KEHADIRAN</span>
                <h2 id="sd-trend-title">Tren kehadiran</h2>
                <p class="sd-card-description">Persentase kehadiran bulanan berdasarkan absensi yang telah tercatat.</p>
            </div>
            <span class="sd-trend-badge"><i aria-hidden="true"></i> Bulanan</span>
        </div>

        <div class="sd-chart" data-attendance-chart data-chart-size="compact" data-trend-id="attendance-trend-data" role="img" aria-label="Grafik tren persentase kehadiran bulanan">
            <div class="sd-chart-empty" data-chart-empty @if ($attendanceTrend->isNotEmpty()) hidden @endif>
                Grafik akan terbentuk setelah absensi tercatat.
            </div>
            <div class="sd-chart-canvas" data-chart-canvas></div>
        </div>
        <script type="application/json" id="attendance-trend-data">@json($attendanceTrend)</script>
        <p class="sd-chart-note">Grafik menunjukkan rasio hadir dari catatan absensi setiap bulan.</p>
    </section>

</div>
@endsection
