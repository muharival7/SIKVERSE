<?php

use App\Enums\UserRole;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\Santri;
use App\Models\User;

function createInputAuditFixture(): array
{
    $suffix = fake()->unique()->numerify('######');

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $periodA = PeriodeSik::create([
        'nama_periode' => 'Periode Input A '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);

    $periodB = PeriodeSik::create([
        'nama_periode' => 'Periode Input B '.$suffix,
        'tahun' => 2027,
        'tanggal_mulai' => '2027-01-01',
        'tanggal_selesai' => '2027-12-31',
        'status' => 'Selesai',
    ]);

    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::create([
        'user_id' => $instructorUser->id,
        'nama' => 'Instruktur Aktif '.$suffix,
        'nip' => '700'.$suffix,
        'program_studi' => 'Sistem Informasi',
        'status' => 'Aktif',
    ]);

    $inactiveInstructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $inactiveInstructor = InfrastrukturSik::create([
        'user_id' => $inactiveInstructorUser->id,
        'nama' => 'Instruktur Nonaktif '.$suffix,
        'nip' => '701'.$suffix,
        'status' => 'Tidak Aktif',
    ]);

    $groupA = Kelompok::create([
        'periode_sik_id' => $periodA->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok Input A '.$suffix,
        'status' => 'Aktif',
    ]);

    $groupA2 = Kelompok::create([
        'periode_sik_id' => $periodA->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok Input A2 '.$suffix,
        'status' => 'Aktif',
    ]);

    $inactiveGroup = Kelompok::create([
        'periode_sik_id' => $periodA->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok Input Nonaktif '.$suffix,
        'status' => 'Tidak Aktif',
    ]);

    $groupB = Kelompok::create([
        'periode_sik_id' => $periodB->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok Input B '.$suffix,
        'status' => 'Aktif',
    ]);

    $santriA = Santri::create([
        'nomor_induk' => '710'.$suffix,
        'nama' => 'Santri Input A '.$suffix,
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);

    $santriB = Santri::create([
        'nomor_induk' => '711'.$suffix,
        'nama' => 'Santri Input B '.$suffix,
        'jenis_kelamin' => 'Perempuan',
        'status' => 'Aktif',
    ]);

    return compact(
        'admin',
        'instructorUser',
        'instructor',
        'inactiveInstructor',
        'periodA',
        'periodB',
        'groupA',
        'groupA2',
        'inactiveGroup',
        'groupB',
        'santriA',
        'santriB'
    );
}

test('formulir anggota periode hanya menawarkan periode dan kelompok aktif', function () {
    $fixture = createInputAuditFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('anggota-periode-sik.create'))
        ->assertOk()
        ->assertSee($fixture['periodA']->nama_periode)
        ->assertDontSee($fixture['periodB']->nama_periode)
        ->assertSee($fixture['groupA']->nama_kelompok)
        ->assertDontSee($fixture['groupB']->nama_kelompok)
        ->assertDontSee($fixture['inactiveGroup']->nama_kelompok)
        ->assertSee('data-periode="'.$fixture['periodA']->id.'"', false)
        ->assertSee('sesuaikanKelompok', false);
});

test('formulir edit anggota mempertahankan periode dan kelompok lama yang sudah nonaktif', function () {
    $fixture = createInputAuditFixture();

    $membership = AnggotaPeriodeSik::create([
        'santri_id' => $fixture['santriA']->id,
        'periode_sik_id' => $fixture['periodA']->id,
        'kelompok_id' => $fixture['groupA']->id,
        'status' => 'Aktif',
    ]);

    $fixture['periodA']->update(['status' => 'Selesai']);
    $fixture['groupA']->update(['status' => 'Tidak Aktif']);

    $groupCount = Kelompok::count();
    $periodCount = PeriodeSik::count();

    $this->actingAs($fixture['admin'])
        ->get(route('anggota-periode-sik.edit', $membership))
        ->assertOk()
        ->assertSee($fixture['periodA']->nama_periode)
        ->assertSee($fixture['groupA']->nama_kelompok)
        ->assertSee('value="'.$fixture['periodA']->id.'" selected', false)
        ->assertSee('data-periode="'.$fixture['periodA']->id.'"', false);

    expect(Kelompok::count())->toBe($groupCount)
        ->and(PeriodeSik::count())->toBe($periodCount);
});

test('anggota periode menolak kelompok dari periode lain', function () {
    $fixture = createInputAuditFixture();

    $groupCount = Kelompok::count();

    $this->actingAs($fixture['admin'])
        ->post(route('anggota-periode-sik.store'), [
            'santri_id' => $fixture['santriA']->id,
            'periode_sik_id' => $fixture['periodA']->id,
            'kelompok_id' => $fixture['groupB']->id,
            'status' => 'Aktif',
        ])
        ->assertSessionHasErrors('kelompok_id', 'Kelompok harus berada pada periode yang dipilih.');

    $this->assertDatabaseMissing('anggota_periode_sik', [
        'santri_id' => $fixture['santriA']->id,
        'periode_sik_id' => $fixture['periodA']->id,
    ]);

    expect(Kelompok::count())->toBe($groupCount);
});

test('anggota periode menolak kelompok nonaktif pada periode yang sama', function () {
    $fixture = createInputAuditFixture();

    $this->actingAs($fixture['admin'])
        ->post(route('anggota-periode-sik.store'), [
            'santri_id' => $fixture['santriA']->id,
            'periode_sik_id' => $fixture['periodA']->id,
            'kelompok_id' => $fixture['inactiveGroup']->id,
            'status' => 'Aktif',
        ])
        ->assertSessionHasErrors('kelompok_id', 'Kelompok yang dipilih tidak aktif.');

    $this->assertDatabaseMissing('anggota_periode_sik', [
        'santri_id' => $fixture['santriA']->id,
    ]);
});

test('perubahan kelompok anggota periode tersimpan pada relasi yang sama', function () {
    $fixture = createInputAuditFixture();

    $membership = AnggotaPeriodeSik::create([
        'santri_id' => $fixture['santriA']->id,
        'periode_sik_id' => $fixture['periodA']->id,
        'kelompok_id' => $fixture['groupA']->id,
        'status' => 'Aktif',
    ]);

    $this->actingAs($fixture['admin'])
        ->put(route('anggota-periode-sik.update', $membership), [
            'santri_id' => $fixture['santriA']->id,
            'periode_sik_id' => $fixture['periodA']->id,
            'kelompok_id' => $fixture['groupA2']->id,
            'status' => 'Aktif',
        ])
        ->assertRedirect(route('anggota-periode-sik.index'));

    $this->assertDatabaseHas('anggota_periode_sik', [
        'id' => $membership->id,
        'santri_id' => $fixture['santriA']->id,
        'periode_sik_id' => $fixture['periodA']->id,
        'kelompok_id' => $fixture['groupA2']->id,
    ]);

    expect(AnggotaPeriodeSik::count())->toBe(1);
});

test('filter santri menggabungkan periode dan kelompok pada satu keanggotaan', function () {
    $fixture = createInputAuditFixture();

    AnggotaPeriodeSik::create([
        'santri_id' => $fixture['santriA']->id,
        'periode_sik_id' => $fixture['periodA']->id,
        'kelompok_id' => $fixture['groupA']->id,
        'status' => 'Aktif',
    ]);

    AnggotaPeriodeSik::create([
        'santri_id' => $fixture['santriB']->id,
        'periode_sik_id' => $fixture['periodB']->id,
        'kelompok_id' => $fixture['groupB']->id,
        'status' => 'Aktif',
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('santris.index', [
            'periode_sik_id' => $fixture['periodA']->id,
            'kelompok_id' => $fixture['groupA']->id,
        ]))
        ->assertOk()
        ->assertSee($fixture['santriA']->nama)
        ->assertDontSee($fixture['santriB']->nama)
        ->assertSee($fixture['groupA']->nama_kelompok)
        ->assertSee($fixture['periodA']->nama_periode)
        ->assertSee('data-periode="'.$fixture['periodA']->id.'"', false);
});

test('filter santri menolak kelompok yang tidak sesuai dengan periode', function () {
    $fixture = createInputAuditFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('santris.index', [
            'periode_sik_id' => $fixture['periodA']->id,
            'kelompok_id' => $fixture['groupB']->id,
        ]))
        ->assertSessionHasErrors('kelompok_id', 'Kelompok harus berada pada periode yang dipilih.');
});

test('detail santri menampilkan kelompok dan periode keanggotaan', function () {
    $fixture = createInputAuditFixture();

    AnggotaPeriodeSik::create([
        'santri_id' => $fixture['santriA']->id,
        'periode_sik_id' => $fixture['periodA']->id,
        'kelompok_id' => $fixture['groupA']->id,
        'status' => 'Aktif',
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('santris.show', $fixture['santriA']))
        ->assertOk()
        ->assertSee('Kelompok & Periode SIK', false)
        ->assertSee($fixture['groupA']->nama_kelompok)
        ->assertSee($fixture['periodA']->nama_periode);
});

test('santri baru dengan NIM non-digit ditolak dan NIM berawalan nol tetap tersimpan', function () {
    config()->set('sik.auth.require_email_verification', false);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->post(route('santris.store'), [
            'nomor_induk' => 'ABC123',
            'nama' => 'Santri NIM Huruf',
            'jenis_kelamin' => 'Laki-laki',
            'status' => 'Aktif',
        ])
        ->assertSessionHasErrors('nomor_induk', 'Nomor Induk/NIM hanya boleh berisi angka 0-9.');

    $this->assertDatabaseMissing('santris', ['nomor_induk' => 'ABC123']);

    $this->actingAs($admin)
        ->post(route('santris.store'), [
            'nomor_induk' => '001234',
            'nama' => 'Santri NIM Nol',
            'jenis_kelamin' => 'Perempuan',
            'status' => 'Aktif',
        ])
        ->assertRedirect(route('santris.index'));

    $this->assertDatabaseHas('santris', ['nomor_induk' => '001234']);
    expect(Santri::query()->where('nomor_induk', '001234')->value('nomor_induk'))->toBe('001234');
});

test('NIM lama non-digit boleh tetap dipakai tetapi perubahan NIM harus digit', function () {
    config()->set('sik.auth.require_email_verification', false);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $santri = Santri::create([
        'nomor_induk' => 'LEGACY-NIM-1',
        'nama' => 'Santri Lama',
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);

    $this->actingAs($admin)
        ->put(route('santris.update', $santri), [
            'nomor_induk' => $santri->nomor_induk,
            'nama' => $santri->nama,
            'jenis_kelamin' => $santri->jenis_kelamin,
            'status' => 'Aktif',
        ])
        ->assertRedirect(route('santris.index'));

    $this->assertDatabaseHas('santris', [
        'id' => $santri->id,
        'nomor_induk' => 'LEGACY-NIM-1',
    ]);

    $this->actingAs($admin)
        ->put(route('santris.update', $santri), [
            'nomor_induk' => '123A',
            'nama' => $santri->nama,
            'jenis_kelamin' => $santri->jenis_kelamin,
            'status' => 'Aktif',
        ])
        ->assertSessionHasErrors('nomor_induk', 'Nomor Induk/NIM hanya boleh berisi angka 0-9.');
});

test('formulir santri membatasi NIM menjadi input digit', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('santris.create'))
        ->assertOk()
        ->assertSee('inputmode="numeric"', false)
        ->assertSee('pattern="[0-9]*"', false)
        ->assertSee('maxlength="255"', false)
        ->assertSee('title="Nomor Induk/NIM hanya boleh berisi angka 0-9."', false);
});

test('NIP dosen baru harus digit dan awalan nol tetap tersimpan', function () {
    config()->set('sik.auth.require_email_verification', false);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Dosen NIP Huruf',
            'email' => 'dosen-huruf@example.test',
            'nip' => 'NIP-ABC',
            'program_studi' => 'Sistem Informasi',
        ])
        ->assertSessionHasErrors('nip', 'NIP hanya boleh berisi angka 0-9.');

    $this->assertDatabaseMissing('infrastruktur_siks', ['nip' => 'NIP-ABC']);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Dosen NIP Nol',
            'email' => 'dosen-nol@example.test',
            'nip' => '009876',
            'program_studi' => 'Sistem Informasi',
        ])
        ->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('infrastruktur_siks', ['nip' => '009876']);
});

test('NIP lama non-digit dipertahankan tetapi perubahan NIP harus digit', function () {
    config()->set('sik.auth.require_email_verification', false);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $user = User::factory()->create([
        'role' => UserRole::InfrastrukturSik,
        'email' => 'dosen-legacy@example.test',
    ]);
    InfrastrukturSik::create([
        'user_id' => $user->id,
        'nama' => $user->name,
        'nip' => 'NIP-LAMA-1',
        'status' => 'Aktif',
    ]);

    $this->actingAs($admin)
        ->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'nip' => 'NIP-LAMA-1',
            'program_studi' => 'Sistem Informasi',
            'status' => 'Aktif',
        ])
        ->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('infrastruktur_siks', [
        'user_id' => $user->id,
        'nip' => 'NIP-LAMA-1',
    ]);

    $this->actingAs($admin)
        ->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'nip' => '123A',
            'program_studi' => 'Sistem Informasi',
            'status' => 'Aktif',
        ])
        ->assertSessionHasErrors('nip', 'NIP hanya boleh berisi angka 0-9.');
});

test('formulir dosen membatasi NIP menjadi input digit', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('users.create'))
        ->assertOk()
        ->assertSee('inputmode="numeric"', false)
        ->assertSee('pattern="[0-9]*"', false)
        ->assertSee('maxlength="50"', false)
        ->assertSee('title="NIP hanya boleh berisi angka 0-9."', false);
});

test('daftar pertemuan hanya menawarkan instruktur pengganti aktif', function () {
    $fixture = createInputAuditFixture();
    $suffix = fake()->unique()->numerify('######');
    $activity = Kegiatan::create([
        'periode_sik_id' => $fixture['periodA']->id,
        'kelompok_id' => null,
        'nama_kegiatan' => 'Kegiatan Input '.$suffix,
        'jenis_kegiatan' => 'Aula',
        'status' => 'Aktif',
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('pertemuan.create'))
        ->assertOk()
        ->assertSee($fixture['instructor']->nama)
        ->assertDontSee($fixture['inactiveInstructor']->nama);

    $this->actingAs($fixture['admin'])
        ->post(route('pertemuan.store'), [
            'kegiatan_id' => $activity->id,
            'pertemuan_ke' => 1,
            'tanggal' => '2026-06-15',
            'status' => 'Terjadwal',
            'instruktur_pengganti_id' => $fixture['inactiveInstructor']->id,
        ])
        ->assertSessionHasErrors('instruktur_pengganti_id', 'Instruktur pengganti harus berstatus Aktif.');
});

test('footer rekap admin tidak menampilkan kalimat rekap seluruh kelompok', function () {
    $fixture = createInputAuditFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('rekap-kehadiran-sik.index'))
        ->assertOk()
        ->assertDontSee('Rekap seluruh kelompok dan kegiatan SIK.')
        ->assertDontSee('Ambang kehadiran: 87,5% dari pertemuan Terlaksana.');

    $this->actingAs($fixture['instructorUser'])
        ->get(route('rekap-kehadiran-sik.index'))
        ->assertOk()
        ->assertDontSee('Rekap seluruh kelompok dan kegiatan SIK.')
        ->assertSee('Ambang kehadiran: 87,5% dari pertemuan Terlaksana.');
});
