@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <span class="text-uppercase fw-bold small"
                  style="color:#760d12;letter-spacing:.14em">
                Input Kehadiran Rabu Mengaji
            </span>
            <h1 class="h2 fw-bold mt-1 mb-1">
                {{ $pertemuan->pertemuan_ke }} · {{ $pertemuan->tanggal?->format('d/m/Y') }}
            </h1>
            <p class="text-muted mb-0">
                {{ $kegiatan->nama_kegiatan }} · Semua Kelompok
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

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">Daftar Santri</h2>
        </div>
        <div class="card-body">
            @if ($anggotaList->isEmpty())
                <div class="alert alert-info">
                    Tidak ada santri aktif dari kelompok aktif pada periode SIK ini.
                </div>
            @else
                <form method="POST" action="{{ route('rabu-mengaji.store') }}" class="row g-3">
                    @csrf
                    <input type="hidden" name="pertemuan_id" value="{{ $pertemuan->id }}">

                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px">No</th>
                                <th>NIM</th>
                                <th>Nama Santri</th>
                                <th>Kelompok Asal</th>
                                <th style="width: 160px">Status Kehadiran</th>
                                <th style="width: 220px">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($anggotaList as $index => $anggota)
                                @php
                                    $santri = $anggota->santri;
                                    $kelompok = $anggota->kelompok;
                                    $existingStatus = $existingAbsensi[$anggota->id] ?? null;
                                    $isExisting = $existingStatus !== null;
                                @endphp
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td class="fw-semibold">{{ $santri?->nomor_induk ?? '—' }}</td>
                                    <td class="fw-semibold">{{ $santri?->nama ?? '—' }}</td>
                                    <td>{{ $kelompok?->nama_kelompok ?? '—' }}</td>
                                    <td>
                                        <select name="status[{{ $anggota->id }}]" class="form-select form-select-sm"
                                            {{ $isExisting ? 'disabled' : 'required' }}
                                        >
                                            <option value="Hadir" {{ old("status.{$anggota->id}", $existingStatus ?? 'Hadir') === 'Hadir' ? 'selected' : '' }}>Hadir</option>
                                            <option value="Izin" {{ old("status.{$anggota->id}", $existingStatus ?? '') === 'Izin' ? 'selected' : '' }}>Izin</option>
                                            <option value="Sakit" {{ old("status.{$anggota->id}", $existingStatus ?? '') === 'Sakit' ? 'selected' : '' }}>Sakit</option>
                                            <option value="Alpha" {{ old("status.{$anggota->id}", $existingStatus ?? '') === 'Alpha' ? 'selected' : '' }}>Alpha</option>
                                        </select>
                                        @if ($isExisting)
                                            <span class="badge bg-secondary text-white ms-2">Sudah tercatat: {{ $existingStatus }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <input type="text" name="keterangan[{{ $anggota->id }}]"
                                            value="{{ old("keterangan.{$anggota->id}") ?? '' }}"
                                            class="form-control form-control-sm"
                                            maxlength="1000"
                                            placeholder="Keterangan opsional"
                                            {{ $isExisting ? 'disabled' : '' }}
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if ($anggotaList->isNotEmpty())
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-success"
                                {{ $anggotaList->every(fn ($a) => isset($existingAbsensi[$a->id])) ? 'disabled' : '' }}
                            >
                                Simpan Kehadiran
                            </button>
                        </div>
                    @endif
                </form>
            @endif
        </div>
    </div>

</div>
@endsection
