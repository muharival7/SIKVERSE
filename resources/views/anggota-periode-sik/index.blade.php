@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h1 class="page-title h3 mb-1">Anggota Periode SIK</h1><p class="text-muted mb-0">Daftar santri yang terdaftar dalam kelompok dan periode.</p></div>
        @unless ($readOnly)<a href="{{ route('anggota-periode-sik.create') }}" class="btn btn-primary">+ Tambah Anggota</a>@endunless
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="card shadow-sm"><div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead><tr><th>No</th><th>Nomor Induk</th><th>Nama</th><th>Periode</th><th>Kelompok</th><th>Tanggal Masuk</th><th>Status</th>@unless ($readOnly)<th>Aksi</th>@endunless</tr></thead>
            <tbody>
                @forelse ($anggota as $item)
                    <tr>
                        <td>{{ $anggota->firstItem() + $loop->index }}</td>
                        <td>{{ $item->santri?->nomor_induk ?? '—' }}</td>
                        <td>{{ $item->santri?->nama ?? '—' }}</td>
                        <td>{{ $item->periode?->nama_periode ?? '—' }}</td>
                        <td>{{ $item->kelompok?->nama_kelompok ?? '—' }}</td>
                        <td>{{ $item->tanggal_masuk?->format('d-m-Y') ?? '—' }}</td>
                        <td><span class="badge {{ $item->status === 'Aktif' ? 'bg-success' : 'bg-secondary' }}">{{ $item->status }}</span></td>
                        @unless ($readOnly)
                            <td><div class="d-flex gap-2"><a href="{{ route('anggota-periode-sik.edit', $item) }}" class="btn btn-sm btn-warning">Edit</a><form action="{{ route('anggota-periode-sik.destroy', $item) }}" method="POST" onsubmit="return confirm('Hapus keanggotaan periode ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger" type="submit">Hapus</button></form></div></td>
                        @endunless
                    </tr>
                @empty
                    <tr><td colspan="{{ $readOnly ? 7 : 8 }}" class="text-center text-muted py-5">Belum ada anggota periode SIK.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($anggota->hasPages())<div class="card-footer bg-white">{{ $anggota->links() }}</div>@endif
    </div>
</div>
@endsection
