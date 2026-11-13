@extends('layouts.app')
@section('content')
<div class="container py-4 py-lg-5">
 <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><h1 class="page-title h3 mb-1">Manajemen Kelompok</h1><p class="text-muted mb-0">Kelola kelompok SIK, periode, dan instruktur tetap.</p></div><a href="{{ route('kelompok.create') }}" class="btn btn-primary">+ Tambah Kelompok</a></div>
 @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
 @if(session('error'))<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
 @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
 <div class="card shadow-sm"><div class="card-header bg-white py-3"><h2 class="h5 fw-bold mb-0">Daftar Kelompok SIK</h2></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered table-hover align-middle mb-0"><thead><tr><th>No</th><th>Nama Kelompok</th><th>Periode SIK</th><th>Instruktur Tetap</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
 @forelse($kelompoks as $kelompok)<tr><td>{{ $kelompoks->firstItem()+$loop->index }}</td><td class="fw-semibold">{{ $kelompok->nama_kelompok }}</td><td>{{ $kelompok->periode->nama_periode ?? '—' }}</td><td>{{ $kelompok->instruktur->nama ?? '—' }}</td><td><span class="badge {{ $kelompok->status === 'Aktif' ? 'bg-success' : 'bg-secondary' }}">{{ $kelompok->status }}</span></td><td><div class="d-flex gap-2"><a href="{{ route('kelompok.edit',$kelompok) }}" class="btn btn-sm btn-warning">Edit</a><form action="{{ route('kelompok.destroy',$kelompok) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus kelompok ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Hapus</button></form></div></td></tr>
 @empty<tr><td colspan="6" class="text-center py-5"><div class="fw-semibold">Belum ada data kelompok</div><div class="text-muted">Tambahkan kelompok untuk mengatur santri dan instruktur.</div></td></tr>@endforelse
 </tbody></table></div></div>@if($kelompoks->hasPages())<div class="card-footer bg-white">{{ $kelompoks->links() }}</div>@endif</div>
</div>
@endsection
