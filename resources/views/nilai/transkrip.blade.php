@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <h1 class="h3 fw-bold">Nilai, Transkrip, dan Kelulusan</h1>
        <p class="text-muted mb-0">Informasi kegiatan SIK untuk {{ $santri->nama }} ({{ $santri->nomor_induk }}).</p>
    </div>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-3">Status Kelulusan</h2>
            @forelse ($santri->anggotaPeriode as $anggota)
                <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom py-2 gap-2">
                    <span>{{ $anggota->periode?->nama_periode ?? 'Periode SIK' }}</span>
                    <span class="badge {{ $anggota->kelulusan?->status === 'Lulus' ? 'bg-success' : ($anggota->kelulusan?->status === 'Tidak Lulus' ? 'bg-danger' : 'bg-secondary') }}">
                        {{ $anggota->kelulusan?->status ?? 'Belum ditetapkan' }}
                    </span>
                </div>
            @empty
                <p class="text-muted mb-0">Belum ada data keanggotaan periode SIK.</p>
            @endforelse
        </div>
    </section>

    <h2 class="h5 fw-bold mb-3">Transkrip Nilai</h2>
    @forelse ($nilai->groupBy('tahun_ajaran') as $tahunAjaran => $nilaiTahun)
        <section class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold mb-3">Tahun Ajaran {{ $tahunAjaran }}</h2>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Semester</th><th>Mata Pelajaran</th><th>Nilai</th><th>Keterangan</th><th>Instruktur</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($nilaiTahun as $catatan)
                                <tr>
                                    <td>{{ $catatan->semester }}</td>
                                    <td>{{ $catatan->mataPelajaran?->nama_mapel ?? 'Mata pelajaran tidak tersedia' }}</td>
                                    <td>{{ number_format((float) $catatan->nilai, 2) }}</td>
                                    <td>{{ $catatan->keterangan ?: '-' }}</td>
                                    <td>{{ $catatan->infrastrukturSik?->nama ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @empty
        <div class="alert alert-info">Belum ada nilai yang tersedia.</div>
    @endforelse
</div>
@endsection
