<?php

namespace Tests\Feature\SPMB;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\ProgramStudi;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\System\MasterReferensi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SpmbGelombangJalurTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private JalurMasuk $jalurA;

    private JalurMasuk $jalurB;

    private ProgramStudi $prodi;

    private MasterReferensi $wni;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
        $this->setUpPassport();

        $role = Role::firstOrCreate(['slug' => 'admin_spmb'], ['name' => 'Admin SPMB', 'is_active' => true]);
        $permission = Permission::firstOrCreate(
            ['slug' => 'spmb.manage'],
            ['name' => 'Kelola Admin SPMB', 'module' => 'spmb', 'action' => 'update'],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        Role::firstOrCreate(['slug' => 'calon_mhs'], ['name' => 'Calon Mahasiswa', 'is_active' => true]);

        $this->admin = User::create([
            'username' => 'admin_gel', 'email' => 'admin_gel@test.com',
            'password' => bcrypt('password'), 'is_active' => true, 'is_verified' => true,
        ]);
        $this->admin->roles()->syncWithoutDetaching([$role->id]);

        $this->prodi = ProgramStudi::create(['kode_prodi' => 'D3-FAR', 'nama' => 'Farmasi', 'jenjang' => 'D3', 'is_active' => true]);
        $this->jalurA = JalurMasuk::create(['kode' => 'JA', 'nama' => 'Jalur A', 'is_active' => true]);
        $this->jalurB = JalurMasuk::create(['kode' => 'JB', 'nama' => 'Jalur B', 'is_active' => true]);
        $this->wni = MasterReferensi::create(['tipe' => 'kewarganegaraan', 'kode' => 'WNI', 'nama' => 'Warga Negara Indonesia', 'is_active' => true]);
    }

    /** @param JalurMasuk|array<int,JalurMasuk>|null $jalur satu jalur, banyak jalur, atau null (gelombang umum) */
    private function gelombang(JalurMasuk|array|null $jalur, string $nama, string $status = 'aktif'): GelombangPenerimaan
    {
        $gelombang = GelombangPenerimaan::create([
            'nama' => $nama, 'tanggal_buka' => '01-01',
            'tanggal_tutup' => '12-31', 'kuota_total' => 10, 'kuota_terisi' => 0,
            'biaya_pendaftaran' => 0, 'status' => $status,
        ]);
        $ids = collect(is_array($jalur) ? $jalur : [$jalur])->filter()->map(fn ($j) => $j->id)->all();
        $gelombang->daftarJalur()->sync($ids);

        return $gelombang;
    }

    private function resolve(JalurMasuk $jalur, string $konteks = 'manual'): array
    {
        return $this->actingAs($this->admin, 'api')
            ->getJson("/api/spmb/gelombang/resolve?jalur_masuk_id={$jalur->id}&konteks={$konteks}")
            ->assertOk()->json('data');
    }

    private function manualPayload(array $override = []): array
    {
        return array_merge([
            'email' => 'calon@test.com', 'program_studi_id' => $this->prodi->id,
            'nama_lengkap' => 'CALON UJI', 'nik' => '3372010101010001',
            'kewarganegaraan_id' => $this->wni->id, 'tanggal_lahir' => '2005-01-01',
            'tempat_lahir' => 'Solo', 'status_sipil' => 'belum_menikah', 'no_hp' => '081234567890',
            'alamat' => 'Jl. Contoh No. 1', 'provinsi' => 'Jawa Tengah', 'kota_kabupaten' => 'Surakarta',
            'kecamatan' => 'Banjarsari',
        ], $override);
    }

    public function test_master_gelombang_can_be_created_without_jalur(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/spmb/gelombang', [
            'nama' => 'Gelombang Umum 1', 'tanggal_buka' => '01-01', 'tanggal_tutup' => '03-31',
            'kuota_total' => 100, 'status' => 'aktif',
        ])->assertStatus(201)->assertJsonPath('data.jalur_masuk_id', null);

        $this->assertDatabaseHas('spmb_gelombang_penerimaan', ['nama' => 'Gelombang Umum 1', 'jalur_masuk_id' => null]);
    }

    public function test_resolve_uses_the_single_active_gelombang_mapped_to_the_jalur(): void
    {
        $mapped = $this->gelombang($this->jalurA, 'Khusus A');
        $this->gelombang(null, 'Umum 1');

        $data = $this->resolve($this->jalurA);

        $this->assertSame('otomatis', $data['mode']);
        $this->assertSame($mapped->id, $data['gelombang']['id']);
        $this->assertSame([], $data['options']);
    }

    public function test_resolve_lets_user_choose_when_several_mapped_gelombang_are_active(): void
    {
        $a1 = $this->gelombang($this->jalurA, 'Khusus A1');
        $a2 = $this->gelombang($this->jalurA, 'Khusus A2');
        $this->gelombang(null, 'Umum 1');

        $data = $this->resolve($this->jalurA);

        $this->assertSame('pilih', $data['mode']);
        $this->assertSame('terpetakan', $data['sumber']);
        $this->assertEqualsCanonicalizing([$a1->id, $a2->id], collect($data['options'])->pluck('id')->all());
    }

    public function test_resolve_offers_only_unmapped_active_gelombang_when_jalur_has_no_active_mapping(): void
    {
        $umum1 = $this->gelombang(null, 'Umum 1');
        $umum2 = $this->gelombang(null, 'Umum 2');
        $this->gelombang(null, 'Umum Draft', 'draft');
        $this->gelombang($this->jalurB, 'Khusus B');           // dipetakan ke jalur lain: dikecualikan
        $this->gelombang($this->jalurA, 'Khusus A Tutup', 'ditutup'); // terpetakan tapi tidak aktif

        $data = $this->resolve($this->jalurA);

        $this->assertSame('pilih', $data['mode']);
        $this->assertSame('umum', $data['sumber']);
        $this->assertEqualsCanonicalizing([$umum1->id, $umum2->id], collect($data['options'])->pluck('id')->all());
    }

    public function test_resolve_reports_unavailable_when_nothing_is_active(): void
    {
        $this->gelombang($this->jalurB, 'Khusus B');

        $this->assertSame('tidak_tersedia', $this->resolve($this->jalurA)['mode']);
    }

    public function test_manual_registration_with_general_gelombang_stores_the_chosen_jalur(): void
    {
        $umum = $this->gelombang(null, 'Umum 1');

        $this->actingAs($this->admin, 'api')->postJson('/api/spmb/pendaftaran/manual', $this->manualPayload([
            'jalur_masuk_id' => $this->jalurA->id, 'gelombang_id' => $umum->id,
        ]))->assertStatus(201);

        $this->assertDatabaseHas('spmb_pendaftaran_calon_mhs', [
            'nama_lengkap' => 'CALON UJI', 'gelombang_id' => $umum->id, 'jalur_masuk_id' => $this->jalurA->id,
        ]);
    }

    public function test_manual_registration_resolves_gelombang_automatically_when_omitted(): void
    {
        $mapped = $this->gelombang($this->jalurA, 'Khusus A');

        $this->actingAs($this->admin, 'api')->postJson('/api/spmb/pendaftaran/manual', $this->manualPayload([
            'jalur_masuk_id' => $this->jalurA->id,
        ]))->assertStatus(201);

        $this->assertDatabaseHas('spmb_pendaftaran_calon_mhs', [
            'nama_lengkap' => 'CALON UJI', 'gelombang_id' => $mapped->id, 'jalur_masuk_id' => $this->jalurA->id,
        ]);
    }

    public function test_manual_registration_rejects_gelombang_mapped_to_another_jalur_or_unresolvable(): void
    {
        $khususB = $this->gelombang($this->jalurB, 'Khusus B');

        $this->actingAs($this->admin, 'api')->postJson('/api/spmb/pendaftaran/manual', $this->manualPayload([
            'jalur_masuk_id' => $this->jalurA->id, 'gelombang_id' => $khususB->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('gelombang_id');

        // Jalur A tidak punya gelombang aktif (hanya gelombang khusus B) sehingga tidak dapat ditentukan otomatis.
        $this->actingAs($this->admin, 'api')->postJson('/api/spmb/pendaftaran/manual', $this->manualPayload([
            'email' => 'lain@test.com', 'nik' => '3372010101010002', 'jalur_masuk_id' => $this->jalurA->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('gelombang_id');

        $this->assertDatabaseCount('spmb_pendaftaran_calon_mhs', 0);
    }

    public function test_admin_edit_validates_gelombang_against_jalur_and_legacy_rows_fall_back_to_gelombang_jalur(): void
    {
        $khususA = $this->gelombang($this->jalurA, 'Khusus A');
        $khususB = $this->gelombang($this->jalurB, 'Khusus B');
        $umum = $this->gelombang(null, 'Umum 1');
        $user = User::create(['username' => 'cm_x', 'email' => 'cm_x@test.com', 'password' => bcrypt('x'), 'is_active' => true, 'is_verified' => true]);
        $p = new PendaftaranCalonMhs([
            'user_id' => $user->id, 'gelombang_id' => $khususA->id, 'program_studi_id' => $this->prodi->id,
            'no_pendaftaran' => 'REG-X', 'nama_lengkap' => 'LAMA', 'status' => 'submitted',
            'status_pembayaran' => 'belum_bayar', 'sumber_pendaftaran' => 'online',
        ]);
        $p->asal_lulusan = 'sekolah';
        $p->save();

        // Data lama tanpa jalur sendiri mengikuti jalur gelombangnya.
        $this->assertSame($this->jalurA->id, $p->fresh()->resolveJalurMasukId());

        // Pindah ke gelombang khusus jalur lain ditolak.
        $this->actingAs($this->admin, 'api')->putJson("/api/spmb/pendaftaran/{$p->id}", [
            'jalur_masuk_id' => $this->jalurA->id, 'gelombang_id' => $khususB->id,
        ])->assertStatus(422)->assertJsonValidationErrors('gelombang_id');

        // Gelombang umum boleh dipakai untuk jalur mana pun.
        $this->actingAs($this->admin, 'api')->putJson("/api/spmb/pendaftaran/{$p->id}", [
            'jalur_masuk_id' => $this->jalurB->id, 'gelombang_id' => $umum->id,
        ])->assertOk();

        $this->assertDatabaseHas('spmb_pendaftaran_calon_mhs', [
            'id' => $p->id, 'gelombang_id' => $umum->id, 'jalur_masuk_id' => $this->jalurB->id,
        ]);
        $this->assertSame($this->jalurB->id, $p->fresh()->resolveJalurMasukId());
    }

    public function test_pendaftaran_list_filter_uses_applicant_jalur_with_legacy_fallback(): void
    {
        $umum = $this->gelombang(null, 'Umum 1');
        $khususA = $this->gelombang($this->jalurA, 'Khusus A');
        $make = function (string $no, ?int $gelombangId, ?int $jalurId) {
            $user = User::create(['username' => 'u'.$no, 'email' => $no.'@test.com', 'password' => bcrypt('x'), 'is_active' => true, 'is_verified' => true]);
            $p = new PendaftaranCalonMhs([
                'user_id' => $user->id, 'gelombang_id' => $gelombangId, 'jalur_masuk_id' => $jalurId,
                'program_studi_id' => $this->prodi->id, 'no_pendaftaran' => $no, 'nama_lengkap' => $no,
                'status' => 'submitted', 'status_pembayaran' => 'belum_bayar', 'sumber_pendaftaran' => 'online',
            ]);
            $p->asal_lulusan = 'sekolah';
            $p->save();
        };
        $make('P-BARU-A', $umum->id, $this->jalurA->id);   // jalur tersimpan pada pendaftar
        $make('P-LAMA-A', $khususA->id, null);             // data lama: ikut jalur gelombang
        $make('P-BARU-B', $umum->id, $this->jalurB->id);

        $numbers = collect($this->actingAs($this->admin, 'api')
            ->getJson('/api/spmb/pendaftaran?jalur_masuk_id='.$this->jalurA->id)->assertOk()->json('data.data'))
            ->pluck('no_pendaftaran')->all();

        $this->assertEqualsCanonicalizing(['P-BARU-A', 'P-LAMA-A'], $numbers);
    }

    public function test_resolve_is_limited_to_admin_spmb_and_calon_mahasiswa(): void
    {
        $this->gelombang(null, 'Umum 1');
        $other = User::create(['username' => 'dosen_x', 'email' => 'dosen_x@test.com', 'password' => bcrypt('x'), 'is_active' => true, 'is_verified' => true]);
        $calon = User::create(['username' => 'calon_x', 'email' => 'calon_x@test.com', 'password' => bcrypt('x'), 'is_active' => true, 'is_verified' => true]);
        $calon->roles()->syncWithoutDetaching([Role::where('slug', 'calon_mhs')->value('id')]);
        $url = '/api/spmb/gelombang/resolve?jalur_masuk_id='.$this->jalurA->id;

        $this->actingAs($other, 'api')->getJson($url)->assertStatus(403);
        $this->actingAs($calon, 'api')->getJson($url.'&konteks=online')->assertOk();
        $this->actingAs($this->admin, 'api')->getJson($url)->assertOk();
        $this->actingAs($this->admin, 'api')->getJson($url.'&konteks=lainnya')->assertStatus(422)->assertJsonValidationErrors('konteks');
    }

    public function test_master_gelombang_can_be_mapped_to_multiple_jalur_and_updated(): void
    {
        $response = $this->actingAs($this->admin, 'api')->postJson('/api/spmb/gelombang', [
            'nama' => 'Gelombang Multi', 'tanggal_buka' => '01-01', 'tanggal_tutup' => '03-31',
            'kuota_total' => 50, 'status' => 'aktif', 'jalur_masuk_ids' => [$this->jalurA->id, $this->jalurB->id],
        ])->assertStatus(201);

        $id = $response->json('data.id');
        $this->assertEqualsCanonicalizing([$this->jalurA->id, $this->jalurB->id], $response->json('data.jalur_masuk_ids'));
        $this->assertEqualsCanonicalizing(['Jalur A', 'Jalur B'], collect($response->json('data.daftar_jalur'))->pluck('nama')->all());
        $this->assertDatabaseCount('spmb_gelombang_jalur_masuk', 2);

        $payload = ['nama' => 'Gelombang Multi', 'tanggal_buka' => '01-01', 'tanggal_tutup' => '03-31', 'kuota_total' => 50, 'status' => 'aktif'];

        // Tanpa jalur_masuk_ids: pemetaan dibiarkan.
        $this->actingAs($this->admin, 'api')->putJson("/api/spmb/gelombang/{$id}", $payload)->assertOk();
        $this->assertDatabaseCount('spmb_gelombang_jalur_masuk', 2);

        // Mengganti pemetaan.
        $this->actingAs($this->admin, 'api')->putJson("/api/spmb/gelombang/{$id}", $payload + ['jalur_masuk_ids' => [$this->jalurB->id]])
            ->assertOk()->assertJsonPath('data.jalur_masuk_ids', [$this->jalurB->id]);

        // Array kosong menjadikannya gelombang umum.
        $this->actingAs($this->admin, 'api')->putJson("/api/spmb/gelombang/{$id}", $payload + ['jalur_masuk_ids' => []])
            ->assertOk()->assertJsonPath('data.jalur_masuk_ids', []);
        $this->assertDatabaseCount('spmb_gelombang_jalur_masuk', 0);

        // Duplikat dan jalur tak dikenal ditolak.
        $this->actingAs($this->admin, 'api')->putJson("/api/spmb/gelombang/{$id}", $payload + ['jalur_masuk_ids' => [$this->jalurA->id, $this->jalurA->id]])
            ->assertStatus(422)->assertJsonValidationErrors('jalur_masuk_ids.0');
        $this->actingAs($this->admin, 'api')->putJson("/api/spmb/gelombang/{$id}", $payload + ['jalur_masuk_ids' => [99999]])
            ->assertStatus(422)->assertJsonValidationErrors('jalur_masuk_ids.0');
    }

    public function test_gelombang_mapped_to_several_jalur_is_resolved_for_each_and_excluded_for_others(): void
    {
        $jalurC = JalurMasuk::create(['kode' => 'JC', 'nama' => 'Jalur C', 'is_active' => true]);
        $multi = $this->gelombang([$this->jalurA, $this->jalurB], 'Gelombang AB');
        $umum = $this->gelombang(null, 'Umum 1');

        $forA = $this->resolve($this->jalurA);
        $forB = $this->resolve($this->jalurB);
        $this->assertSame('otomatis', $forA['mode']);
        $this->assertSame($multi->id, $forA['gelombang']['id']);
        $this->assertSame($multi->id, $forB['gelombang']['id']);
        $this->assertEqualsCanonicalizing([$this->jalurA->id, $this->jalurB->id], $forA['gelombang']['jalur_masuk_ids']);

        // Jalur C tidak ditawari gelombang yang dipetakan ke jalur lain.
        $forC = $this->resolve($jalurC);
        $this->assertSame('pilih', $forC['mode']);
        $this->assertSame([$umum->id], collect($forC['options'])->pluck('id')->all());
    }

    public function test_manual_registration_accepts_any_of_the_gelombang_jalur_and_rejects_others(): void
    {
        $jalurC = JalurMasuk::create(['kode' => 'JC', 'nama' => 'Jalur C', 'is_active' => true]);
        $multi = $this->gelombang([$this->jalurA, $this->jalurB], 'Gelombang AB');

        $this->actingAs($this->admin, 'api')->postJson('/api/spmb/pendaftaran/manual', $this->manualPayload([
            'jalur_masuk_id' => $this->jalurB->id, 'gelombang_id' => $multi->id,
        ]))->assertStatus(201);
        $this->assertDatabaseHas('spmb_pendaftaran_calon_mhs', ['gelombang_id' => $multi->id, 'jalur_masuk_id' => $this->jalurB->id]);

        $this->actingAs($this->admin, 'api')->postJson('/api/spmb/pendaftaran/manual', $this->manualPayload([
            'email' => 'c@test.com', 'nik' => '3372010101010009', 'jalur_masuk_id' => $jalurC->id, 'gelombang_id' => $multi->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('gelombang_id');
    }

    public function test_legacy_single_jalur_column_is_still_honoured_when_no_mapping_rows_exist(): void
    {
        $legacy = GelombangPenerimaan::create([
            'jalur_masuk_id' => $this->jalurA->id, 'nama' => 'Legacy A', 'tanggal_buka' => '01-01',
            'tanggal_tutup' => '12-31', 'kuota_total' => 10, 'kuota_terisi' => 0, 'biaya_pendaftaran' => 0, 'status' => 'aktif',
        ]);

        $this->assertSame([$this->jalurA->id], $legacy->jalurIds());
        $this->assertSame($this->jalurA->id, $legacy->singleJalurId());
        $this->assertSame($legacy->id, $this->resolve($this->jalurA)['gelombang']['id']);
        $this->assertSame('tidak_tersedia', $this->resolve($this->jalurB)['mode']);
    }

    public function test_pivot_migration_moves_legacy_jalur_into_mapping_table(): void
    {
        // Simulasi data sebelum migrasi: gelombang dengan kolom lama terisi dan tanpa baris penghubung.
        $g = GelombangPenerimaan::create([
            'jalur_masuk_id' => $this->jalurA->id, 'nama' => 'Sebelum Migrasi', 'tanggal_buka' => '01-01',
            'tanggal_tutup' => '12-31', 'kuota_total' => 10, 'kuota_terisi' => 0, 'biaya_pendaftaran' => 0, 'status' => 'aktif',
        ]);
        \Illuminate\Support\Facades\DB::table('spmb_gelombang_jalur_masuk')->delete();

        $migration = require base_path('database/migrations/2026_11_26_300000_create_spmb_gelombang_jalur_masuk_pivot.php');
        \Illuminate\Support\Facades\Schema::drop('spmb_gelombang_jalur_masuk');
        $migration->up();

        $this->assertDatabaseHas('spmb_gelombang_jalur_masuk', ['gelombang_id' => $g->id, 'jalur_masuk_id' => $this->jalurA->id]);
        $this->assertNull(\Illuminate\Support\Facades\DB::table('spmb_gelombang_penerimaan')->where('id', $g->id)->value('jalur_masuk_id'));
        $this->assertSame([$this->jalurA->id], $g->fresh()->jalurIds());
    }

    public function test_jalur_ids_does_not_fail_when_model_is_loaded_with_limited_columns(): void
    {
        $this->gelombang([$this->jalurA, $this->jalurB], 'Gelombang AB');
        $this->gelombang(null, 'Umum 1');

        $partial = GelombangPenerimaan::query()->select('id', 'nama')->orderBy('id')->get();

        $this->assertEqualsCanonicalizing([$this->jalurA->id, $this->jalurB->id], $partial[0]->jalurIds());
        $this->assertSame([], $partial[1]->jalurIds());
        $this->assertTrue($partial[1]->isUmum());
    }
}
