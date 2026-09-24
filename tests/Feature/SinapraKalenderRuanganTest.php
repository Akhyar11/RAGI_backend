<?php

namespace Tests\Feature;

use App\Models\Gedung;
use App\Models\PeminjamanRuangan;
use App\Models\Ruangan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SinapraKalenderRuanganTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gedung $gedungA;
    protected Ruangan $ruangan101;
    protected Ruangan $ruangan102;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        $this->user = User::factory()->create();

        $this->gedungA = Gedung::create([
            'kode' => 'GDA',
            'nama' => 'Gedung Rektorat A',
            'jumlah_lantai' => 4,
            'status' => 'aktif',
        ]);

        $this->ruangan101 = Ruangan::create([
            'gedung_id' => $this->gedungA->id,
            'kode' => 'R101',
            'nama' => 'Ruang Sidang Utama',
            'kapasitas' => 50,
            'lantai' => 1,
            'tipe' => 'aula',
            'status' => 'aktif',
        ]);

        $this->ruangan102 = Ruangan::create([
            'gedung_id' => $this->gedungA->id,
            'kode' => 'R102',
            'nama' => 'Laboratorium Komputer Dasar',
            'kapasitas' => 30,
            'lantai' => 1,
            'tipe' => 'lab',
            'status' => 'aktif',
        ]);
    }

    public function test_cannot_access_kalender_without_authentication()
    {
        $response = $this->getJson('/api/sinapra/kalender-ruangan');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_kalender_schedule()
    {
        Passport::actingAs($this->user);

        $response = $this->getJson('/api/sinapra/kalender-ruangan');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => [
                    'start_date',
                    'end_date',
                    'total_events',
                ],
            ]);
    }

    public function test_kalender_includes_sinapra_peminjaman_events()
    {
        Passport::actingAs($this->user);

        $today = Carbon::today()->format('Y-m-d');

        PeminjamanRuangan::create([
            'ruangan_id' => $this->ruangan101->id,
            'user_id' => $this->user->id,
            'tanggal' => $today,
            'jam_mulai' => '09:00:00',
            'jam_selesai' => '11:00:00',
            'keperluan' => 'Rapat Senat Akademik',
            'status' => 'disetujui',
        ]);

        $response = $this->getJson('/api/sinapra/kalender-ruangan?start_date=' . $today . '&end_date=' . $today);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $this->assertEquals('sinapra', $data[0]['source']);
        $this->assertEquals('Rapat Senat Akademik', $data[0]['title']);
        $this->assertEquals($this->ruangan101->id, $data[0]['ruangan_id']);
        $this->assertEquals('disetujui', $data[0]['status']);
    }

    public function test_filter_by_ruangan_and_source()
    {
        Passport::actingAs($this->user);

        $today = Carbon::today()->format('Y-m-d');

        PeminjamanRuangan::create([
            'ruangan_id' => $this->ruangan101->id,
            'user_id' => $this->user->id,
            'tanggal' => $today,
            'jam_mulai' => '13:00:00',
            'jam_selesai' => '15:00:00',
            'keperluan' => 'Sosialisasi Kurikulum',
            'status' => 'disetujui',
        ]);

        // Filter untuk ruangan 102 yang tidak memiliki agenda
        $response102 = $this->getJson('/api/sinapra/kalender-ruangan?start_date=' . $today . '&end_date=' . $today . '&ruangan_id=' . $this->ruangan102->id);
        $response102->assertStatus(200);
        $this->assertEmpty($response102->json('data'));

        // Filter source sinapra untuk ruangan 101
        $response101 = $this->getJson('/api/sinapra/kalender-ruangan?start_date=' . $today . '&end_date=' . $today . '&ruangan_id=' . $this->ruangan101->id . '&source=sinapra');
        $response101->assertStatus(200);
        $this->assertCount(1, $response101->json('data'));
    }

    public function test_invalid_date_range_validation()
    {
        Passport::actingAs($this->user);

        $response = $this->getJson('/api/sinapra/kalender-ruangan?start_date=2026-09-25&end_date=2026-09-20');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }
}
