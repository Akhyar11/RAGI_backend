<?php

namespace Tests\Unit\Services\Siakad;

use App\Models\Lms\LmsNotifikasi;
use App\Models\User;
use App\Services\Siakad\LmsNotificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit test untuk logika internal LmsNotificationService.
 *
 * Endpoint/HTTP sudah dicakup oleh SiakadLmsNotifikasiTest; di sini yang diuji
 * adalah aturan bisnis service: dedup, scope personal, filter, dan pagination.
 */
class LmsNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private LmsNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LmsNotificationService::class);
    }

    public function test_push_dedup_key_mencegah_notifikasi_ganda(): void
    {
        $user = User::factory()->create();
        $options = [
            'dedup_key' => 'deadline_tugas:12:20261124',
            'rute' => '/lms/tugas/12',
            'prioritas' => LmsNotifikasi::PRIORITAS_TINGGI,
        ];

        $pertama = $this->service->push($user->id, LmsNotifikasi::JENIS_DEADLINE_TUGAS, 'Jatuh tempo', 'Pesan', $options);
        $kedua = $this->service->push($user->id, LmsNotifikasi::JENIS_DEADLINE_TUGAS, 'Jatuh tempo', 'Pesan', $options);

        $this->assertSame($pertama->id, $kedua->id);
        $this->assertSame(1, LmsNotifikasi::query()->where('user_id', $user->id)->count());
        $this->assertSame('/lms/tugas/12', $pertama->rute);
        $this->assertSame(LmsNotifikasi::PRIORITAS_TINGGI, $pertama->prioritas);
    }

    public function test_push_many_memilah_user_unik_dan_melewati_id_invalid(): void
    {
        $pertama = User::factory()->create();
        $kedua = User::factory()->create();

        $dibuat = $this->service->pushMany(
            [$pertama->id, $pertama->id, $kedua->id, 0, -1],
            LmsNotifikasi::JENIS_INFO,
            'Info',
            null,
            ['dedup_key' => 'info:global']
        );

        $this->assertSame(2, $dibuat);
        $this->assertSame(2, LmsNotifikasi::query()->count());
    }

    public function test_index_hanya_mengembalikan_notifikasi_milik_user_dengan_filter(): void
    {
        $user = User::factory()->create();
        $lain = User::factory()->create();

        $this->service->push($user->id, LmsNotifikasi::JENIS_DEADLINE_TUGAS, 'Tugas segera', 'Cari kata kunci');
        $this->service->push($user->id, LmsNotifikasi::JENIS_INFO, 'Info biasa', 'Pesan lain');
        $this->service->push($lain->id, LmsNotifikasi::JENIS_INFO, 'Info orang lain', 'Cari kata kunci');

        $hasil = $this->service->index($user, [
            'search' => 'kata kunci',
            'jenis' => LmsNotifikasi::JENIS_DEADLINE_TUGAS,
            'per_page' => 999,
            'sort_by' => 'kolom_tidak_ada',
        ]);

        $this->assertSame(1, $hasil->total());
        $this->assertSame(100, $hasil->perPage());
        $this->assertSame('Tugas segera', $hasil->items()[0]->judul);
    }

    public function test_mark_as_read_dan_mark_all_terikat_pada_user(): void
    {
        $user = User::factory()->create();
        $lain = User::factory()->create();

        $notifikasiSaya = $this->service->push($user->id, LmsNotifikasi::JENIS_INFO, 'Milik saya');
        $notifikasiLain = $this->service->push($lain->id, LmsNotifikasi::JENIS_INFO, 'Milik orang lain');

        $this->assertSame(1, $this->service->unreadCount($user));
        $this->assertSame(1, $this->service->unreadCount($lain));

        $this->expectException(ModelNotFoundException::class);
        $this->service->markAsRead($user, $notifikasiLain->id);
    }

    public function test_mark_as_read_menandai_waktu_baca_dan_mark_all_hanya_user_ini(): void
    {
        $user = User::factory()->create();
        $lain = User::factory()->create();

        $pertama = $this->service->push($user->id, LmsNotifikasi::JENIS_INFO, 'Pertama');
        $kedua = $this->service->push($user->id, LmsNotifikasi::JENIS_INFO, 'Kedua');
        $padaLain = $this->service->push($lain->id, LmsNotifikasi::JENIS_INFO, 'Lain');

        $ditandai = $this->service->markAsRead($user, $pertama->id);
        $this->assertTrue((bool) $ditandai->is_read);
        $this->assertNotNull($ditandai->read_at);

        $this->assertSame(1, $this->service->markAllAsRead($user));
        $this->assertSame(0, $this->service->unreadCount($user));
        $this->assertSame(1, $this->service->unreadCount($lain));

        $this->assertTrue($kedua->fresh()->is_read);
        $this->assertFalse($padaLain->fresh()->is_read);
    }
}
