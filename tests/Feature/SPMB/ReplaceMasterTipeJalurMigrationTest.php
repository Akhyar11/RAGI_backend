<?php

namespace Tests\Feature\SPMB;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReplaceMasterTipeJalurMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_drops_legacy_tipe_data_and_keeps_registration_path_in_wave(): void
    {
        Artisan::call('migrate', ['--force' => true]);

        $this->assertFalse(Schema::hasTable('core_master_tipe_jalur'));
        $this->assertFalse(Schema::hasTable('spmb_master_tipe_jalur_alur'));
        $this->assertFalse(Schema::hasTable('spmb_pendaftaran_alur'));
        $this->assertFalse(Schema::hasTable('spmb_kode_diskon_jenis_pendaftaran'));
        $this->assertFalse(Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'master_tipe_jalur_id'));
        $this->assertTrue(Schema::hasColumn('spmb_master_biaya', 'jalur_masuk_id'));
        $this->assertFalse(Schema::hasColumn('spmb_master_biaya', 'master_tipe_jalur_id'));
        $this->assertFalse(Schema::hasColumn('spmb_jalur_masuk', 'tipe'));
        $this->assertFalse(Schema::hasColumn('spmb_jalur_masuk', 'ada_ujian_tulis'));

        $this->assertSame(0, DB::table('spmb_master_biaya')->count());
        $this->assertSame(0, DB::table('spmb_master_biaya_item')->count());

        $application = DB::table('spmb_pendaftaran_calon_mhs')->first();
        if ($application) {
            $this->assertDatabaseHas('spmb_gelombang_penerimaan', [
                'id' => $application->gelombang_id,
            ]);
        }
    }
}