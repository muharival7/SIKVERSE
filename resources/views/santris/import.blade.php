@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5" style="max-width:1120px">
    <a href="{{ route('santris.index') }}" class="small text-decoration-none">← Kembali ke Data Santri</a>
    <div class="mt-3 mb-4">
        <span class="text-uppercase fw-bold small" style="color:#760d12;letter-spacing:.14em">Import data</span>
        <h1 class="h2 fw-bold mt-1 mb-1">Import Santri dari Excel</h1>
        <p class="text-muted mb-0">Unggah file, periksa setiap baris, lengkapi data wajib, lalu setujui penyimpanan.</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert"><strong>File belum dapat diproses.</strong><ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    @if ($failure = session('import_failure'))
        <div class="alert alert-danger"><strong>Import gagal.</strong> {{ $failure['message'] }}</div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <section class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold">1. Unduh template</h2>
                    <p class="text-muted">Template membantu menyiapkan kolom yang dikenali sistem.</p>
                    <a class="btn btn-outline-danger" href="{{ route('santris.import.template') }}"><x-sik-icon name="download" class="me-1" /> Unduh template Excel</a>
                    <hr class="my-4">
                    <h3 class="h6 fw-bold">Ketentuan data</h3>
                    <ul class="small text-muted ps-3 mb-0">
                        <li><strong>Wajib:</strong> NIM, Nama, Jenis Kelamin, Kelompok, dan periode yang dapat dikenali.</li>
                        <li>Header dapat memakai nama seperti NIM/Nomor Induk, Nama, Program Studi, Kelompok/Kelas, dan Periode.</li>
                        <li>Judul semester dan tahun di atas tabel dapat digunakan untuk mengenali periode.</li>
                        <li>Kelompok harus sudah aktif pada periode tersebut. Buat kelompok dan tetapkan instruktur sebelum mengimpor.</li>
                        <li>Program Studi boleh kosong. Status kosong mengikuti status awal database: Aktif.</li>
                        @if (config('sik.auth.require_email_verification'))
                            <li class="text-danger">Email asli wajib diisi karena verifikasi email aktif.</li>
                        @else
                            <li>Email boleh kosong; sistem membuat username dari NIM. Password sementara tersedia setelah import.</li>
                        @endif
                        <li>Format harus .xlsx, maksimal 10 MB dan 1.500 baris data. Kolom NIM sebaiknya bertipe teks.</li>
                    </ul>
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-7">
            <section class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold">2. Unggah dan pratinjau</h2>
                    <p class="text-muted">Belum ada data yang disimpan pada tahap ini. Admin meninjau hasil terlebih dahulu dan menekan Import untuk menyimpan.</p>
                    <form action="{{ route('santris.import.preview') }}" method="POST" enctype="multipart/form-data" class="vstack gap-3">
                        @csrf
                        <div>
                            <label for="santri-xlsx" class="form-label fw-semibold">File Excel (.xlsx)</label>
                            <input id="santri-xlsx" class="form-control" type="file" name="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                            <div class="form-text">Sistem akan mencari header di beberapa baris pertama dan mengabaikan baris kosong.</div>
                        </div>
                        <div><button class="btn btn-danger" type="submit">Validasi dan lihat pratinjau</button></div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
