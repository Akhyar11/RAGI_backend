<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Siakad\Fakultas;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\ProgramStudi;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SikeuPengajuanRbacAndReconcileTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_user_cannot_access_or_approve_pengajuan_operasional_or_kas(): void
    {
        $role = Role::create(['name' => 'Mahasiswa', 'slug' => 'mahasiswa']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user, 'api')
            ->getJson('/api/v1/sikeu/pengajuan-operasional')
            ->assertStatus(403);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/sikeu/pengajuan-operasional/1/approve', ['aksi' => 'approve'])
            ->assertStatus(403);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/sikeu/pengajuan-operasional/1/pencairan', [])
            ->assertStatus(403);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/sikeu/lpj/1/verifikasi', ['aksi' => 'approve'])
            ->assertStatus(403);

        $this->actingAs($user, 'api')
            ->getJson('/api/v1/sikeu/pengajuan-kas')
            ->assertStatus(403);
    }

    public function test_reconcile_legacy_payments_links_orphan_tagihan_by_nim_in_catatan_and_piutang_search_matches_catatan(): void
    {
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole->id);

        $fakultas = Fakultas::create(['kode' => 'FIK', 'nama' => 'Fakultas Ilmu Kesehatan', 'is_active' => true]);
        $prodi = ProgramStudi::create([
            'fakultas_id' => $fakultas->id,
            'kode_prodi' => 'MIK',
            'nama' => 'Manajemen Informasi Kesehatan',
            'jenjang' => 'D3',
            'is_active' => true,
        ]);

        $mhs = Mahasiswa::create([
            'program_studi_id' => $prodi->id,
            'nim' => '202620056',
            'nama_lengkap' => 'ANA NASIROTUL QORIAH',
            'angkatan' => 2026,
            'status' => 'A',
        ]);

        $tagihan = TagihanMahasiswa::create([
            'mahasiswa_id' => null,
            'calon_mahasiswa_id' => null,
            'nomor_tagihan' => 'TRX-LEG-8B15C6C68FA3',
            'total_tagihan' => 4500000,
            'total_potongan' => 0,
            'total_denda' => 0,
            'total_bayar' => 4500000,
            'status' => 'lunas',
            'source_system' => 'LEGACY_MIGRATOR',
        ]);

        Pembayaran::create([
            'tagihan_id' => $tagihan->id,
            'kode_transaksi' => 'C-12511202600009',
            'jumlah_bayar' => 4500000,
            'waktu_bayar' => now(),
            'channel_bayar' => 'TRANSFER_BANK',
            'status' => 'success',
            'catatan' => 'NIM: 202620056 | BNI 21-11-2026 JAM 16.30 AN PENGRIIM ANA NASIROTUL QORIAH',
        ]);

        // Search in piutang matches catatan even before reconcile
        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/sikeu/piutang?status=all&search=202620056')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Run reconcile command
        $this->artisan('sikeu:reconcile-legacy-payments')
            ->assertSuccessful();

        $tagihan->refresh();
        $this->assertEquals($mhs->id, $tagihan->mahasiswa_id);
        $this->assertEquals('202620056', $tagihan->h2h_custid);
    }

    public function test_observer_auto_links_orphan_tagihan_on_mahasiswa_create_and_grouped_piutang_recap(): void
    {
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole->id);

        $fakultas = Fakultas::create(['kode' => 'FIK', 'nama' => 'Fakultas Ilmu Kesehatan', 'is_active' => true]);
        $prodi = ProgramStudi::create([
            'fakultas_id' => $fakultas->id,
            'kode_prodi' => 'MIK',
            'nama' => 'Manajemen Informasi Kesehatan',
            'jenjang' => 'D3',
            'is_active' => true,
        ]);

        // Two orphan tagihan before Mahasiswa is created
        $t1 = TagihanMahasiswa::create([
            'mahasiswa_id' => null,
            'h2h_custid' => 'F26999',
            'nomor_tagihan' => 'TGH-LEG-AUTO-1',
            'total_tagihan' => 2000000,
            'total_potongan' => 0,
            'total_denda' => 0,
            'total_bayar' => 500000,
            'status' => 'sebagian',
        ]);
        $t2 = TagihanMahasiswa::create([
            'mahasiswa_id' => null,
            'h2h_custid' => 'F26999',
            'nomor_tagihan' => 'TGH-LEG-AUTO-2',
            'total_tagihan' => 1000000,
            'total_potongan' => 0,
            'total_denda' => 0,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
        ]);

        // Creating Mahasiswa automatically triggers MahasiswaObserver -> LegacyTagihanLinkerService
        $mhs = Mahasiswa::create([
            'program_studi_id' => $prodi->id,
            'nim' => 'F26999',
            'nama_lengkap' => 'BUDI OTOMATIS',
            'angkatan' => 2026,
            'status' => 'A',
        ]);

        $this->assertEquals($mhs->id, $t1->fresh()->mahasiswa_id);
        $this->assertEquals($mhs->id, $t2->fresh()->mahasiswa_id);

        // Grouped piutang recap returns 1 row for BUDI OTOMATIS with 2 tagihan and sisa 2.500.000
        $res = $this->actingAs($admin, 'api')
            ->getJson('/api/v1/sikeu/piutang?group_by=mahasiswa&search=F26999')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertEquals(2, $res->json('data.0.jumlah_tagihan'));
        $this->assertEquals(2500000, $res->json('data.0.sisa_piutang'));
    }
}
