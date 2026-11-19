@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><h1 class="page-title h3 mb-1">Kelulusan SIK</h1><p class="text-muted mb-0">Kelola hasil kelulusan peserta per periode. Status evaluasi mengikuti ambang kehadiran yang dihitung dari pertemuan Terlaksana.</p></div><a class="btn btn-primary" href="{{ route('kelulusan-sik.create') }}">+ Catat Kelulusan</a></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <div class="card shadow-sm"><div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0"><thead><tr><th>No</th><th>Nomor Induk</th><th>Santri</th><th>Periode</th><th>Kelompok</th><th>Kehadiran</th><th>Status Evaluasi</th><th>Tanggal</th><th>Alasan Evaluasi</th><th>Aksi</th></tr></thead>
            <tbody>@forelse ($kelulusan as $item)
                <tr>
                    <td>{{ $kelulusan->firstItem() + $loop->index }}</td>
                    <td>{{ $item->anggotaPeriodeSik?->santri?->nomor_induk ?? '—' }}</td>
                    <td>{{ $item->anggotaPeriodeSik?->santri?->nama ?? '—' }}</td>
                    <td>{{ $item->anggotaPeriodeSik?->periode?->nama_periode ?? '—' }}</td>
                    <td>{{ $item->anggotaPeriodeSik?->kelompok?->nama_kelompok ?? '—' }}</td>
                    <td>{{ $item->attendance_display }}</td>
                    <td><span class="badge {{ $item->evaluasi_badge_class }}">{{ $item->evaluasi_label }}</span></td>
                    <td>{{ $item->tanggal_kelulusan?->format('d-m-Y') ?? '—' }}</td>
                    <td><small class="text-muted">{{ $item->alasan_kehadiran }}</small></td>
                    <td><div class="d-flex gap-2"><a class="btn btn-sm btn-warning" href="{{ route('kelulusan-sik.edit', $item) }}">Edit</a><form action="{{ route('kelulusan-sik.destroy', $item) }}" method="POST" onsubmit="return confirm('Hapus catatan kelulusan ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Hapus</button></form></div></td>
                </tr>
            @empty<tr><td colspan="10" class="text-center text-muted py-5">Belum ada catatan kelulusan.</td></tr>@endforelse</tbody>
        </table>
    </div>@if ($kelulusan->hasPages())<div class="card-footer bg-white">{{ $kelulusan->links() }}</div>@endif</div>
</div>
@endsection
