@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="mb-4"><h1 class="page-title h3 mb-1">Terbitkan Sertifikat</h1><p class="text-muted mb-0">Unggah berkas PDF untuk kelulusan yang sudah dinyatakan lulus.</p></div>
    @if ($kelulusan->isEmpty())
        <div class="alert alert-info" role="status">Belum ada peserta yang berstatus lulus. Tetapkan status kelulusan terlebih dahulu sebelum menerbitkan sertifikat.</div>
    @endif
    <div class="card shadow-sm"><div class="card-body p-4">
        <form action="{{ route('sertifikat-sik.store') }}" method="POST" enctype="multipart/form-data">@csrf
            @include('sertifikat-sik.partials.form', ['item' => null])
            <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" @disabled($kelulusan->isEmpty())>Simpan Sertifikat</button><a class="btn btn-outline-secondary" href="{{ route('sertifikat-sik.index') }}">Batal</a></div>
        </form>
    </div></div>
</div>
@endsection
