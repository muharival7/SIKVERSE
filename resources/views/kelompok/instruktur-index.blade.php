@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="mb-4"><h1 class="page-title h3 mb-1">Kelompok Saya</h1><p class="text-muted mb-0">Kelompok yang ditugaskan kepada akun instruktur ini.</p></div>
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-bordered table-hover align-middle mb-0"><thead><tr><th>No</th><th>Nama Kelompok</th><th>Periode</th><th>Status</th></tr></thead><tbody>
        @forelse ($kelompoks as $item)<tr><td>{{ $kelompoks->firstItem() + $loop->index }}</td><td>{{ $item->nama_kelompok }}</td><td>{{ $item->periode?->nama_periode ?? '—' }}</td><td>{{ $item->status }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted py-5">Belum ada kelompok yang ditugaskan.</td></tr>@endforelse
    </tbody></table></div>@if ($kelompoks->hasPages())<div class="card-footer bg-white">{{ $kelompoks->links() }}</div>@endif</div>
</div>
@endsection
