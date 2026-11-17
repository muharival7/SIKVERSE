@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5 sertifikat-page">

    <header class="sertifikat-page-header">
        <div>
            <span class="sertifikat-eyebrow">SIKVERSE · SERTIFIKAT KELULUSAN</span>
            <h1 class="page-title">Edit Sertifikat</h1>
            <p>Perbarui nomor, tanggal, keterangan, atau berkas sertifikat yang tersimpan.</p>
        </div>
    </header>

    <div class="sertifikat-form-shell">
        <form action="{{ route('sertifikat-sik.update', $sertifikat) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @include('sertifikat-sik.partials.form', ['item' => $sertifikat])

            <div class="sertifikat-form-actions">
                <button class="btn btn-primary">Simpan Perubahan</button>
                <a class="btn btn-outline-secondary" href="{{ route('sertifikat-sik.index') }}">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
