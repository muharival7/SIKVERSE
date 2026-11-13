@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <h1 class="h3 fw-bold">Input dan Pengelolaan Nilai</h1>
        <p class="text-muted mb-0">Nilai hanya dapat dikelola untuk santri di kelompok aktif yang menjadi tanggung jawab Anda.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @forelse ($santris as $santri)
        <section class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="mb-3">
                    <h2 class="h5 fw-bold mb-1">{{ $santri->nama }}</h2>
                    <div class="small text-muted">NIM: {{ $santri->nomor_induk }}</div>
                </div>

                <h3 class="h6 fw-semibold">Nilai yang Anda kelola</h3>
                @forelse ($santri->nilai as $catatan)
                    <form method="POST" action="{{ route('nilai.update', $catatan) }}" class="border rounded p-3 mb-3">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="santri_id" value="{{ $santri->id }}">
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="mapel-{{ $catatan->id }}">Mata pelajaran</label>
                                <select class="form-select" id="mapel-{{ $catatan->id }}" name="mata_pelajaran_id" required>
                                    @foreach ($mataPelajaran as $mapel)
                                        <option value="{{ $mapel->id }}" @selected($catatan->mata_pelajaran_id === $mapel->id)>{{ $mapel->nama_mapel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label" for="semester-{{ $catatan->id }}">Semester</label>
                                <input class="form-control" id="semester-{{ $catatan->id }}" name="semester" value="{{ $catatan->semester }}" maxlength="50" required>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label" for="tahun-{{ $catatan->id }}">Tahun ajaran</label>
                                <input class="form-control" id="tahun-{{ $catatan->id }}" name="tahun_ajaran" value="{{ $catatan->tahun_ajaran }}" maxlength="20" required>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label" for="nilai-{{ $catatan->id }}">Nilai (0–100)</label>
                                <input class="form-control" id="nilai-{{ $catatan->id }}" type="number" name="nilai" min="0" max="100" step="0.01" value="{{ $catatan->nilai }}" required>
                            </div>
                            <div class="col-6 col-md-2">
                                <button class="btn btn-outline-primary w-100" type="submit">Simpan</button>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="keterangan-{{ $catatan->id }}">Keterangan</label>
                                <textarea class="form-control" id="keterangan-{{ $catatan->id }}" name="keterangan" rows="2" maxlength="1000">{{ $catatan->keterangan }}</textarea>
                            </div>
                        </div>
                    </form>
                @empty
                    <p class="text-muted">Belum ada nilai yang dicatat oleh Anda.</p>
                @endforelse

                @if ($mataPelajaran->isNotEmpty())
                    <details class="mt-3">
                        <summary class="btn btn-primary btn-sm">Tambah nilai</summary>
                        <form method="POST" action="{{ route('nilai.store') }}" class="border rounded p-3 mt-3">
                            @csrf
                            <input type="hidden" name="santri_id" value="{{ $santri->id }}">
                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-md-4">
                                    <label class="form-label" for="mapel-baru-{{ $santri->id }}">Mata pelajaran</label>
                                    <select class="form-select" id="mapel-baru-{{ $santri->id }}" name="mata_pelajaran_id" required>
                                        <option value="">Pilih mata pelajaran</option>
                                        @foreach ($mataPelajaran as $mapel)
                                            <option value="{{ $mapel->id }}">{{ $mapel->nama_mapel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label" for="semester-baru-{{ $santri->id }}">Semester</label>
                                    <input class="form-control" id="semester-baru-{{ $santri->id }}" name="semester" maxlength="50" required>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label" for="tahun-baru-{{ $santri->id }}">Tahun ajaran</label>
                                    <input class="form-control" id="tahun-baru-{{ $santri->id }}" name="tahun_ajaran" maxlength="20" placeholder="2026/2027" required>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label" for="nilai-baru-{{ $santri->id }}">Nilai (0–100)</label>
                                    <input class="form-control" id="nilai-baru-{{ $santri->id }}" type="number" name="nilai" min="0" max="100" step="0.01" required>
                                </div>
                                <div class="col-6 col-md-2">
                                    <button class="btn btn-primary w-100" type="submit">Simpan</button>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="keterangan-baru-{{ $santri->id }}">Keterangan</label>
                                    <textarea class="form-control" id="keterangan-baru-{{ $santri->id }}" name="keterangan" rows="2" maxlength="1000"></textarea>
                                </div>
                            </div>
                        </form>
                    </details>
                @else
                    <div class="alert alert-warning mb-0">Admin belum menambahkan mata pelajaran aktif.</div>
                @endif
            </div>
        </section>
    @empty
        <div class="alert alert-info">Belum ada santri aktif dalam kelompok yang Anda ampu.</div>
    @endforelse
</div>
@endsection
