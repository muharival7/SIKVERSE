@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="mb-4"><h1 class="page-title h3 mb-1">Jadwal Saya</h1><p class="text-muted mb-0">Jadwal yang terhubung ke akun instruktur ini.</p></div>
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-bordered table-hover align-middle mb-0"><thead><tr><th>No</th><th>Kelas</th><th>Hari</th><th>Waktu</th><th>Ruang</th><th>Status</th></tr></thead><tbody>
        @forelse ($jadwal as $item)<tr><td>{{ $jadwal->firstItem() + $loop->index }}</td><td>{{ $item->kelas?->nama_kelas ?? '—' }}</td><td>{{ $item->hari }}</td><td>{{ substr($item->waktu_mulai, 0, 5) }}–{{ substr($item->waktu_selesai, 0, 5) }}</td><td>{{ $item->ruang }}</td><td>{{ $item->status ? 'Aktif' : 'Tidak Aktif' }}</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-5">Belum ada jadwal untuk akun instruktur ini.</td></tr>@endforelse
    </tbody></table></div>@if ($jadwal->hasPages())<div class="card-footer bg-white">{{ $jadwal->links() }}</div>@endif</div>
</div>
@endsection
