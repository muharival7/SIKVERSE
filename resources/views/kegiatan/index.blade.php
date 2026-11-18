
@extends('layouts.app')

@section('content')

<div class="container py-5">

    {{-- HEADER --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">

        <div>
            <h2 class="page-title mb-2">Data Kegiatan SIK</h2>
            <p class="text-muted mb-0">
                Kelola kegiatan SIK berdasarkan periode dan kelompok.
            </p>
        </div>

        <a href="{{ route('kegiatan.create') }}" class="btn btn-primary mt-3 mt-md-0">
            + Tambah Kegiatan
        </a>

    </div>

    {{-- ALERT --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- TABEL --}}
    <div class="card shadow-sm">

        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold">Daftar Kegiatan</h5>
        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead>
                        <tr>
                            <th width="60">No</th>
                            <th>Nama Kegiatan</th>
                            <th>Jenis</th>
                            <th>Periode</th>
                            <th>Kelompok</th>
                            <th>Status</th>
                            <th width="180">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse($kegiatans as $item)

                            <tr>
                                <td>
                                    {{ $kegiatans->firstItem() + $loop->index }}
                                </td>

                                <td class="fw-semibold">
                                    {{ $item->nama_kegiatan }}
                                </td>

                                <td>
                                    <span class="badge bg-info text-dark">
                                        {{ $item->jenis_kegiatan }}
                                    </span>
                                </td>

                                <td>
                                    {{ $item->periode?->nama_periode ?? '-' }}
                                </td>

                                <td>
                                    {{ $item->kelompok?->nama_kelompok ?? 'Semua Kelompok' }}
                                </td>

                                <td>{{ $item->status }}</td>

                                <td>

                                    <div class="d-flex gap-2">

                                        <a href="{{ route('kegiatan.edit', $item->id) }}"
                                           class="btn btn-warning btn-sm">
                                            Edit
                                        </a>

                                        <form action="{{ route('kegiatan.destroy', $item->id) }}"
                                              method="POST"
                                              onsubmit="return confirm('Yakin ingin menghapus kegiatan ini?')">

                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" class="btn btn-danger btn-sm">
                                                Hapus
                                            </button>

                                        </form>

                                    </div>

                                </td>
                            </tr>

                        @empty

                            <tr>
                                <td colspan="7" class="text-center py-5">

                                    <div class="text-muted">
                                        <h5>Belum ada data kegiatan</h5>
                                        <p class="mb-0">
                                            Silakan klik Tambah Kegiatan untuk memasukkan data baru.
                                        </p>
                                    </div>

                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

        @if($kegiatans->hasPages())
            <div class="card-footer bg-white">
                {{ $kegiatans->links() }}
            </div>
        @endif

    </div>

</div>

@endsection
