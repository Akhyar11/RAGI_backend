<?php

namespace Tests\Feature;

use App\Models\Simpeg\MasterJenisCuti;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpegMasterJenisCutiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->admin = User::factory()->create([
            'id' => 1,
            'username' => 'superadmin',
            'email' => 'admin@campus.ac.id',
        ]);

        $unitKerja = UnitKerja::create([
            'nama' => 'Biro Kepegawaian',
            'kode' => 'BKD',
            'tipe' => 'biro',
            'is_active' => true,
        ]);

        $this->pegawai = Pegawai::create([
            'user_id' => $this->admin->id,
            'unit_kerja_id' => $unitKerja->id,
            'nip' => '198501152010121001',
            'nik' => '3271011501850002',
            'nama_lengkap' => 'Dr. Wasis Utama, M.T.',
            'tanggal_lahir' => '1985-01-15',
            'tempat_lahir' => 'Bandung',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'jenis_pegawai' => 'dosen',
            'status_kepegawaian' => 'tetap_yayasan',
            'tanggal_masuk' => '2010-12-01',
            'status' => 'aktif',
        ]);
    }

    public function test_can_get_master_jenis_cuti_list()
    {
        MasterJenisCuti::create([
            'nama' => 'Izin Sakit',
            'kode' => 'IZIN_SAKIT',
            'tipe_durasi' => 'fleksibel',
            'durasi_hari' => 0,
            'satuan' => 'hari',
            'lampiran_wajib' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/master-jenis-cuti');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'nama',
                        'tipe_durasi',
                        'durasi_hari',
                        'satuan',
                        'lampiran_wajib',
                        'is_active',
                    ]
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ]
            ]);
    }

    public function test_admin_can_create_master_jenis_cuti()
    {
        $payload = [
            'nama' => 'Izin Menikah',
            'kode' => 'IZIN_MENIKAH',
            'tipe_durasi' => 'ditetapkan',
            'durasi_hari' => 14,
            'satuan' => 'hari',
            'lampiran_wajib' => false,
            'keterangan' => 'Izin pernikahan pegawai 14 hari.',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/master-jenis-cuti', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nama' => 'Izin Menikah',
                    'tipe_durasi' => 'ditetapkan',
                    'durasi_hari' => 14,
                ]
            ]);

        $this->assertDatabaseHas('simpeg_master_jenis_cuti', [
            'nama' => 'Izin Menikah',
            'tipe_durasi' => 'ditetapkan',
            'durasi_hari' => 14,
        ]);
    }

    public function test_admin_can_create_master_jenis_cuti_fleksibel_with_zero_durasi_hari()
    {
        $payload = [
            'nama' => 'Cuti Sakit Fleksibel',
            'kode' => 'CUTI_SAKIT_FLEKSIBEL',
            'tipe_durasi' => 'fleksibel',
            'durasi_hari' => 0,
            'satuan' => 'hari',
            'lampiran_wajib' => true,
            'keterangan' => 'Memerlukan surat keterangan dokter/klinik',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/master-jenis-cuti', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nama' => 'Cuti Sakit Fleksibel',
                    'tipe_durasi' => 'fleksibel',
                    'durasi_hari' => 0,
                ]
            ]);

        $this->assertDatabaseHas('simpeg_master_jenis_cuti', [
            'nama' => 'Cuti Sakit Fleksibel',
            'tipe_durasi' => 'fleksibel',
            'durasi_hari' => 0,
        ]);
    }

    public function test_fixed_duration_pengajuan_cuti_auto_calculates_dates()
    {
        $master = MasterJenisCuti::create([
            'nama' => 'Izin Menikah',
            'kode' => 'IZIN_MENIKAH',
            'tipe_durasi' => 'ditetapkan',
            'durasi_hari' => 14,
            'satuan' => 'hari',
            'lampiran_wajib' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/cuti', [
                'pegawai_id' => $this->pegawai->id,
                'master_jenis_cuti_id' => $master->id,
                'tanggal_mulai' => '2026-11-01',
                'alasan' => 'Pernikahan di luar kota',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'master_jenis_cuti_id' => $master->id,
                    'tanggal_mulai' => '2026-11-01',
                    'tanggal_selesai' => '2026-11-14',
                    'jumlah_hari' => 14,
                ]
            ]);

        $this->assertDatabaseHas('simpeg_pengajuan_cuti', [
            'pegawai_id' => $this->pegawai->id,
            'master_jenis_cuti_id' => $master->id,
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-11-14',
            'jumlah_hari' => 14,
        ]);
    }

    public function test_flexible_duration_pengajuan_cuti_accepts_custom_dates()
    {
        $master = MasterJenisCuti::create([
            'nama' => 'Cuti Tahunan',
            'kode' => 'CUTI_TAHUNAN',
            'tipe_durasi' => 'fleksibel',
            'durasi_hari' => 0,
            'satuan' => 'hari',
            'lampiran_wajib' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/cuti', [
                'pegawai_id' => $this->pegawai->id,
                'master_jenis_cuti_id' => $master->id,
                'tanggal_mulai' => '2026-12-01',
                'tanggal_selesai' => '2026-12-05',
                'jumlah_hari' => 5,
                'alasan' => 'Liburan akhir tahun bersama keluarga',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'master_jenis_cuti_id' => $master->id,
                    'tanggal_mulai' => '2026-12-01',
                    'tanggal_selesai' => '2026-12-05',
                    'jumlah_hari' => 5,
                ]
            ]);
    }
}
