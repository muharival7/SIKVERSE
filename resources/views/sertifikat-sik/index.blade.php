@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5 sertifikat-page">

    <header class="sertifikat-page-header">
        <div>
            <span class="sertifikat-eyebrow">SIKVERSE · SERTIFIKAT KELULUSAN</span>
            <h1 class="page-title">Sertifikat SIK</h1>
            <p>Kelola berkas sertifikat untuk peserta yang telah dinyatakan lulus dan memenuhi ambang kehadiran.</p>
        </div>

        <a class="btn btn-primary" href="{{ route('sertifikat-sik.create') }}">
            + Terbitkan Sertifikat
        </a>
    </header>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    <div class="sertifikat-table-card">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nomor Sertifikat</th>
                        <th>Santri</th>
                        <th>Periode</th>
                        <th>Tanggal Terbit</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($sertifikat as $item)
                        <tr>
                            <td>{{ $sertifikat->firstItem() + $loop->index }}</td>
                            <td><strong>{{ $item->nomor_sertifikat }}</strong></td>
                            <td>{{ $item->kelulusan?->anggotaPeriodeSik?->santri?->nama ?? '—' }}</td>
                            <td>{{ $item->kelulusan?->anggotaPeriodeSik?->periode?->nama_periode ?? '—' }}</td>
                            <td>{{ $item->tanggal_terbit?->format('d-m-Y') ?? '—' }}</td>
                            <td>
                                <div class="d-flex flex-wrap">
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="{{ route('sertifikat-sik.preview', $item) }}"
                                       target="_blank"
                                       rel="noopener">
                                        Lihat
                                    </a>
                                    <a class="btn btn-sm btn-warning" href="{{ route('sertifikat-sik.edit', $item) }}">
                                        Edit
                                    </a>
                                    <form action="{{ route('sertifikat-sik.destroy', $item) }}"
                                          method="POST"
                                          onsubmit="return confirm('Hapus sertifikat ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="sertifikat-empty">
                                Belum ada sertifikat yang diterbitkan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($sertifikat->hasPages())
            <div class="card-footer bg-white">
                {{ $sertifikat->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
