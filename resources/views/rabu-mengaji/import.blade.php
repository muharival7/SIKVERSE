@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <span class="text-uppercase fw-bold small"
                  style="color:#760d12;letter-spacing:.14em">
                Impor Kehadiran Rabu Mengaji
            </span>
            <h1 class="h2 fw-bold mt-1 mb-1">Upload Excel</h1>
            <p class="text-muted mb-0">
                Impor kehadiran santri dari file Excel. Format kolom: NIM, Nama Santri, Tanggal, Status Kehadiran, Keterangan.
            </p>
        </div>

        <a class="btn btn-outline-secondary" href="{{ route('rabu-mengaji.index') }}">
            Kembali ke Daftar
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0">Pilih Pertemuan dan Unggah File</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('rabu-mengaji.import.preview') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="pertemuan_id" class="form-label">Pertemuan Rabu Mengaji <span class="text-danger">*</span></label>
                            <select id="pertemuan_id" name="pertemuan_id" class="form-select" required>
                                <option value="">Pilih pertemuan</option>
                                @foreach ($pertemuan as $item)
                                    <option value="{{ $item->id }}">
                                        {{ $item->pertemuan_ke }} · {{ $item->tanggal?->format('d/m/Y') }}
                                        ({{ $item->kegiatan?->nama_kegiatan }})
                                        @if ($item->status !== 'Terjadwal' && $item->status !== 'Terlaksana')
                                            <span class="text-danger">[{{ $item->status }}]</span>
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @if ($errors->has('pertemuan_id'))
                                <div class="invalid-feedback d-block">{{ $errors->first('pertemuan_id') }}</div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="file" class="form-label">File Excel <span class="text-danger">*</span></label>
                            <input id="file" type="file" name="file" class="form-control" accept=".xlsx" required>
                            <div class="form-text">Hanya file .xlsx, maksimal 10 MB.</div>
                            @if ($errors->has('file'))
                                <div class="invalid-feedback d-block">{{ $errors->first('file') }}</div>
                            @endif
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('rabu-mengaji.import.template') }}">
                                Unduh Template
                            </a>
                            <button class="btn btn-primary btn-sm" type="submit">Pratinjau Data</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0">Panduan Format</h2>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><strong>Kolom wajib:</strong> NIM, Nama Santri, Tanggal, Status Kehadiran</li>
                        <li class="mb-2"><strong>Kolom opsional:</strong> Keterangan</li>
                        <li class="mb-2"><strong>Status:</strong> Hadir, Izin, Sakit, Alpha</li>
                        <li class="mb-2"><strong>Tanggal:</strong> Harus sama dengan tanggal pertemuan yang dipilih</li>
                        <li class="mb-2"><strong>NIM:</strong> Hanya angka 0-9, awalan 0 tetap ditulis, dan harus terdaftar sebagai santri aktif</li>
                        <li class="mb-2"><strong>Duplikat:</strong> NIM yang sama dalam satu file hanya dihitung sekali</li>
                        <li class="mb-2"><strong>Sudah ada:</strong> NIM yang sudah memiliki absensi pada pertemuan dilewati</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
