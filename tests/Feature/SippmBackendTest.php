<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Simpeg\Pegawai;
use App\Models\Sippm\SkemaKegiatan;
use App\Models\Sippm\PeriodeHibah;
use App\Models\Sippm\ProposalKegiatan;
use App\Models\Sippm\ReviewerKegiatan;
use App\Models\Sippm\KontrakKegiatan;
use App\Models\Sippm\PublikasiIlmiah;
use App\Models\Sippm\HkiDanBuku;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SippmBackendTest extends TestCase
{
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Get or create test user for auth
        $this->user = User::first() ?? User::factory()->create();
    }

    public function test_can_get_skema_and_periode_master_data(): void
    {
        $response = $this->actingAs($this->user, 'api')->getJson('/api/sippm/skema');
        $response->assertStatus(200)
                 ->assertJsonStructure(['status', 'data']);

        $periodeResponse = $this->actingAs($this->user, 'api')->getJson('/api/sippm/periode');
        $periodeResponse->assertStatus(200)
                        ->assertJsonStructure(['status', 'data']);
    }

    public function test_can_list_proposals(): void
    {
        $response = $this->actingAs($this->user, 'api')->getJson('/api/sippm/proposal');
        $response->assertStatus(200)
                 ->assertJsonStructure(['status', 'data']);
    }

    public function test_can_fetch_upm_iku_metrics(): void
    {
        $response = $this->actingAs($this->user, 'api')->getJson('/api/sippm/integration/upm-iku-metrics?tahun=2026');
        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonStructure(['status', 'data' => ['tahun_anggaran', 'iku_5', 'iku_6', 'total_riset_aktif']]);
    }

    public function test_can_list_publikasi_and_hki(): void
    {
        $pubResponse = $this->actingAs($this->user, 'api')->getJson('/api/sippm/luaran/publikasi');
        $pubResponse->assertStatus(200);

        $hkiResponse = $this->actingAs($this->user, 'api')->getJson('/api/sippm/luaran/hki');
        $hkiResponse->assertStatus(200);
    }
}
