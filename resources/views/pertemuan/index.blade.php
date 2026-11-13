@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h1 class="page-title h3 mb-1">Pertemuan SIK</h1><p class="text-muted mb-0">Kelola jadwal pertemuan dari kegiatan SIK.</p></div>
        <a class="btn btn-primary" href="{{ route('pertemuan.create') }}">+ Tambah Pertemuan</a>
    </div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <div class="card shadow-sm"><div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead><tr><th>No</th><th>Kegiatan</th><th>Periode</th><th>Pertemuan</th><th>Tanggal</th><th>Waktu</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse ($pertemuan as $item)
                    <tr>
                        <td>{{ $pertemuan->firstItem() + $loop->index }}</td>
                        <td>{{ $item->kegiatan?->nama_kegiatan ?? '—' }}</td>
                        <td>{{ $item->kegiatan?->periode?->nama_periode ?? '—' }}</td>
                        <td>{{ $item->pertemuan_ke }}</td>
                        <td>{{ $item->tanggal?->format('d-m-Y') }}</td>
                        <td>{{ $item->waktu_mulai ? substr($item->waktu_mulai, 0, 5) : '—' }}–{{ $item->waktu_selesai ? substr($item->waktu_selesai, 0, 5) : '—' }}</td>
                        <td>{{ $item->status }}</td>
                        <td><div class="d-flex gap-2"><a class="btn btn-sm btn-warning" href="{{ route('pertemuan.edit', $item) }}">Edit</a><form action="{{ route('pertemuan.destroy', $item) }}" method="POST" onsubmit="return confirm('Hapus pertemuan ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger" type="submit">Hapus</button></form></div></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5">Belum ada pertemuan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>@if ($pertemuan->hasPages())<div class="card-footer bg-white">{{ $pertemuan->links() }}</div>@endif</div>
</div>
@endsection
