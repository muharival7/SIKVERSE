@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <span class="text-uppercase fw-bold small" style="color:#760d12;letter-spacing:.12em">Riwayat kehadiran</span>
            <h1 class="h2 fw-bold mb-1">{{ $instructor->nama ?: $instructor->user?->name ?: 'Dosen' }}</h1>
            <p class="text-muted mb-0">NIP: {{ $instructor->nip ?: 'Belum tersedia' }}</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('rekap-absensi-dosen.index', array_filter($filters)) }}">Kembali ke rekap</a>
            @if ($tableAvailable)
                <a class="btn btn-outline-danger" href="{{ route('rekap-absensi-dosen.export', array_filter(['periode_sik_id' => $filters['periode_sik_id'], 'infrastruktur_sik_id' => $instructor->id])) }}">Unduh CSV</a>
            @endif
        </div>
    </div>

    @if (! $tableAvailable)
        <div class="alert alert-info" role="status">Tabel absensi dosen belum tersedia. Jalankan migration absensi dosen setelah membuat backup database.</div>
    @else
        <form method="GET" action="{{ route('rekap-absensi-dosen.show', $instructor) }}" class="card border-0 shadow-sm mb-4">
            <div class="card-body row g-3 align-items-end">
                <div class="col-12 col-md-9">
                    <label class="form-label" for="periode_sik_id">Periode</label>
                    <select id="periode_sik_id" name="periode_sik_id" class="form-select">
                        <option value="">Semua periode</option>
                        @foreach ($periods as $periode)
                            <option value="{{ $periode->id }}" @selected($filters['periode_sik_id'] === $periode->id)>{{ $periode->nama_periode }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-danger flex-grow-1">Tampilkan</button>
                    <a class="btn btn-outline-secondary" href="{{ route('rekap-absensi-dosen.show', $instructor) }}">Reset</a>
                </div>
            </div>
        </form>

        <div class="row g-3 mb-4">
            @foreach ([['Jumlah pertemuan', $totalMeetings], ['Hadir', $totalPresent], ['Tidak hadir', $totalAbsent], ['Persentase hadir', number_format($attendancePercentage, 1).'%']] as [$label, $value])
                <div class="col-6 col-xl-3"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><span class="text-muted">{{ $label }}</span><div class="fs-3 fw-bold" style="color:#760d12">{{ is_numeric($value) ? number_format($value) : $value }}</div></div></div></div>
            @endforeach
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3"><h2 class="h5 mb-0">Detail riwayat kehadiran</h2></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Tanggal</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Status</th><th>Keterangan</th><th>Dicatat Oleh</th></tr></thead>
                    <tbody>
                        @forelse ($absensi as $catatan)
                            <tr>
                                <td>{{ $catatan->tanggal->format('d/m/Y') }}</td>
                                <td>{{ $catatan->waktu_masuk ?: '—' }}</td>
                                <td>{{ $catatan->waktu_pulang ?: '—' }}</td>
                                <td><span class="badge rounded-pill {{ $catatan->status === 'Hadir' ? 'text-bg-success' : ($catatan->status === 'Alpha' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ $catatan->status }}</span></td>
                                <td>{{ $catatan->keterangan ?: '—' }}</td>
                                <td>{{ $catatan->pencatat?->name ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">Belum ada riwayat kehadiran untuk dosen ini pada filter terpilih.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($absensi->hasPages())<div class="card-footer bg-white">{{ $absensi->links() }}</div>@endif
        </div>
    @endif
</div>
@endsection
