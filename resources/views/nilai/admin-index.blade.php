@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <h1 class="h3 fw-bold">Nilai Santri</h1>
        <p class="text-muted mb-0">Pantau nilai yang dicatat instruktur pada kelompok aktif SIK.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted">Total Catatan Nilai</div>
                <div class="fs-3 fw-bold">{{ number_format($summary['total_nilai']) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted">Santri Dinilai</div>
                <div class="fs-3 fw-bold">{{ number_format($summary['total_santri']) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted">Mata Pelajaran</div>
                <div class="fs-3 fw-bold">{{ number_format($summary['total_mata_pelajaran']) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted">Rata-rata Nilai</div>
                <div class="fs-3 fw-bold">{{ $summary['rata_rata'] !== null ? number_format($summary['rata_rata'], 2, ',', '.') : '—' }}</div>
            </div></div>
        </div>
    </div>

    <form class="card border-0 shadow-sm mb-4" method="GET" action="{{ route('nilai.index') }}">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="periode-filter">Periode SIK</label>
                    <select class="form-select" id="periode-filter" name="periode_sik_id">
                        <option value="">Semua periode</option>
                        @foreach ($periods as $periode)
                            <option value="{{ $periode->id }}" @selected((string) ($filters['periode_sik_id'] ?? '') === (string) $periode->id)>{{ $periode->nama_periode }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="kelompok-filter">Kelompok</label>
                    <select class="form-select" id="kelompok-filter" name="kelompok_id">
                        <option value="">Semua kelompok</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected((string) ($filters['kelompok_id'] ?? '') === (string) $group->id)>{{ $group->nama_kelompok }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="semester-filter">Semester</label>
                    <input class="form-control" id="semester-filter" name="semester" value="{{ $filters['semester'] ?? '' }}" maxlength="50">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="tahun-filter">Tahun ajaran</label>
                    <input class="form-control" id="tahun-filter" name="tahun_ajaran" value="{{ $filters['tahun_ajaran'] ?? '' }}" maxlength="20" placeholder="2026/2027">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="q-filter">Cari santri</label>
                    <input class="form-control" id="q-filter" name="q" value="{{ $search }}" maxlength="100" placeholder="Nama atau NIM">
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-primary" type="submit">Tampilkan</button>
                <a class="btn btn-outline-secondary" href="{{ route('nilai.index') }}">Reset</a>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 fw-bold mb-0">Daftar Nilai</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>Nama Santri</th>
                        <th>Nomor Induk</th>
                        <th>Mata Pelajaran</th>
                        <th>Semester</th>
                        <th>Tahun Ajaran</th>
                        <th>Nilai</th>
                        <th>Instruktur</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($nilai as $item)
                        <tr>
                            <td>{{ $nilai->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $item->santri?->nama ?? '-' }}</td>
                            <td>{{ $item->santri?->nomor_induk ?? '-' }}</td>
                            <td>{{ $item->mataPelajaran?->nama_mapel ?? '-' }}</td>
                            <td>{{ $item->semester }}</td>
                            <td>{{ $item->tahun_ajaran }}</td>
                            <td>{{ $item->nilai }}</td>
                            <td>{{ $item->infrastrukturSik?->nama ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">Belum ada nilai yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($nilai->hasPages())
            <div class="card-footer bg-white">{{ $nilai->links() }}</div>
        @endif
    </div>
</div>
@endsection
