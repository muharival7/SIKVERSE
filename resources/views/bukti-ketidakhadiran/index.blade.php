@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4">
    <div class="card border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,#12304a,#245778)">
        <div class="card-body p-4 text-white">
            <h1 class="h3 fw-bold mb-2">Verifikasi Bukti Izin dan Sakit</h1>
            <p class="mb-0 text-white-50">Periksa lampiran yang dikirim santri dan catat keputusan verifikasi.</p>
        </div>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle mb-0">
                <thead class="table-light"><tr><th>Santri</th><th>Kegiatan</th><th>Jenis</th><th>Bukti</th><th>Status</th><th>Catatan Pemeriksaan</th></tr></thead>
                <tbody>
                    @forelse ($bukti as $item)
                        <tr>
                            <td>{{ $item->absensi?->anggotaPeriode?->santri?->nama ?? 'Nama tidak tersedia' }}</td>
                            <td>{{ $item->absensi?->pertemuan?->kegiatan?->nama_kegiatan ?? 'Kegiatan SIK' }}<div class="small text-muted">Pertemuan {{ $item->absensi?->pertemuan?->pertemuan_ke ?? '-' }}</div></td>
                            <td>{{ $item->jenis_bukti }}</td>
                            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('bukti-ketidakhadiran.download', $item) }}">Unduh Bukti</a></td>
                            <td><span class="badge {{ $item->status_verifikasi === 'Diterima' ? 'bg-success' : ($item->status_verifikasi === 'Ditolak' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $item->status_verifikasi }}</span></td>
                            <td>
                                @if ($item->status_verifikasi === 'Menunggu')
                                    <form action="{{ route('verifikasi-izin.update', $item) }}" method="POST" x-data="{ status: 'Diterima' }" class="d-flex flex-column gap-2">
                                        @csrf @method('PATCH')
                                        <select class="form-select form-select-sm" name="status_verifikasi" x-model="status" required>
                                            <option value="Diterima">Terima</option>
                                            <option value="Ditolak">Tolak</option>
                                        </select>
                                        <textarea class="form-control form-control-sm" name="catatan_pemeriksaan" rows="2" placeholder="Catatan pemeriksaan" x-bind:required="status === 'Ditolak'"></textarea>
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Simpan Pemeriksaan</button>
                                    </form>
                                @else
                                    <div>{{ $item->catatan_pemeriksaan ?: '—' }}</div>
                                    <div class="small text-muted">{{ $item->pemeriksa?->nama ?? ($isAdmin ? 'Admin' : '—') }} · {{ $item->tanggal_pemeriksaan?->format('d M Y H:i') ?? '—' }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">Belum ada bukti izin atau sakit yang dikirim.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($bukti->hasPages())<div class="card-footer bg-white">{{ $bukti->links() }}</div>@endif
    </div>
</div>
@endsection
