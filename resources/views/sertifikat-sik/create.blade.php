@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5 sertifikat-page">

    <header class="sertifikat-page-header">
        <div>
            <span class="sertifikat-eyebrow">SIKVERSE · SERTIFIKAT KELULUSAN</span>
            <h1 class="page-title">Terbitkan Sertifikat</h1>
            <p>Unggah berkas PDF untuk catatan kelulusan dengan keputusan Admin terbaru Lulus.</p>
        </div>
    </header>

    @if ($kelulusan->isEmpty())
        <div class="alert alert-info" role="status">
            Belum ada peserta yang berstatus lulus. Tetapkan status kelulusan terlebih dahulu sebelum menerbitkan sertifikat.
        </div>
    @endif

    <div class="sertifikat-form-shell">
        <form action="{{ route('sertifikat-sik.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            @include('sertifikat-sik.partials.form', ['item' => null])

            <div class="sertifikat-form-actions">
                <button class="btn btn-primary" @disabled($kelulusan->isEmpty())>
                    Simpan Sertifikat
                </button>
                <a class="btn btn-outline-secondary" href="{{ route('sertifikat-sik.index') }}">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
