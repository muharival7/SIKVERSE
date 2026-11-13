@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><h1 class="page-title h3 mb-1">Sertifikat SIK</h1><p class="text-muted mb-0">Kelola sertifikat untuk peserta yang telah lulus.</p></div><a class="btn btn-primary" href="{{ route('sertifikat-sik.create') }}">+ Terbitkan Sertifikat</a></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="card shadow-sm"><div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0"><thead><tr><th>No</th><th>Nomor Sertifikat</th><th>Santri</th><th>Periode</th><th>Tanggal Terbit</th><th>Aksi</th></tr></thead>
            <tbody>@forelse ($sertifikat as $item)
                <tr><td>{{ $sertifikat->firstItem() + $loop->index }}</td><td>{{ $item->nomor_sertifikat }}</td><td>{{ $item->kelulusan?->anggotaPeriodeSik?->santri?->nama ?? '—' }}</td><td>{{ $item->kelulusan?->anggotaPeriodeSik?->periode?->nama_periode ?? '—' }}</td><td>{{ $item->tanggal_terbit?->format('d-m-Y') ?? '—' }}</td><td><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="{{ route('sertifikat-sik.download', $item) }}">Unduh</a><a class="btn btn-sm btn-warning" href="{{ route('sertifikat-sik.edit', $item) }}">Edit</a><form action="{{ route('sertifikat-sik.destroy', $item) }}" method="POST" onsubmit="return confirm('Hapus sertifikat ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Hapus</button></form></div></td></tr>
            @empty<tr><td colspan="6" class="text-center text-muted py-5">Belum ada sertifikat yang diterbitkan.</td></tr>@endforelse</tbody>
        </table>
    </div>@if ($sertifikat->hasPages())<div class="card-footer bg-white">{{ $sertifikat->links() }}</div>@endif</div>
</div>
@endsection
