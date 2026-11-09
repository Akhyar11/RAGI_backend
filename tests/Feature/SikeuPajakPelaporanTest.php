<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Menu;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\JurnalUmum;
use App\Models\User;
use Database\Seeders\Sikeu\SikeuAkuntansiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SikeuPajakPelaporanTest extends TestCase
{
    use RefreshDatabase;

    protected User $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SikeuAkuntansiSeeder::class);

        $permission = Permission::create([
            'name' => 'Kelola nama akun pajak',
            'slug' => 'sikeu.pajak_akun.manage',
            'module' => 'sikeu',
            'action' => 'update',
            'description' => 'Kelola alias akun untuk pelaporan pajak',
        ]);
        $role = Role::create([
            'name' => 'Auditor Keuangan Test',
            'slug' => 'auditor_keuangan_test',
            'description' => 'Role pengujian',
            'is_active' => true,
        ]);
        $role->permissions()->attach($permission->id);

        $this->auditor = User::factory()->create();
        $this->auditor->roles()->attach($role->id);
        foreach ([
            '/sikeu/pajak/pelaporan-akun',
            '/sikeu/akuntansi/coa',
            '/sikeu/akuntansi/jurnal',
            '/sikeu/akuntansi/buku-besar',
        ] as $index => $url) {
            $menu = Menu::create([
                'name' => 'Menu SIKEU ' . $index,
                'url' => $url,
                'icon' => 'FaFileAlt',
                'module' => 'sikeu',
                'permission_id' => $url === '/sikeu/pajak/pelaporan-akun' ? $permission->id : null,
                'order_index' => $index + 1,
                'is_active' => true,
            ]);
            $role->menus()->attach($menu->id);
        }
        Passport::actingAs($this->auditor);
    }

    public function test_auditor_can_configure_private_alias_then_publish_it_without_changing_original_coa_or_journal(): void
    {
        $account = AkunKeuangan::where('kode_akun', '101.01')->firstOrFail();
        $journal = JurnalUmum::create([
            'nomor_jurnal' => 'JRN-TAX-ALIAS-TEST',
            'tanggal_jurnal' => now()->toDateString(),
            'jenis_sumber' => 'penyesuaian',
            'keterangan' => 'Pengujian nama akun pajak',
            'status_posting' => 'posted',
            'total_debet' => 100,
            'total_kredit' => 100,
        ]);
        DetailJurnalUmum::create([
            'jurnal_id' => $journal->id,
            'akun_id' => $account->id,
            'debet' => 100,
            'kredit' => 0,
        ]);

        $this->putJson("/api/v1/sikeu/pajak/pelaporan-akun/{$account->id}", [
            'nama_akun_pajak' => 'Kas dan Setara Kas Pajak',
        ])->assertOk()->assertJsonPath('data.nama_akun_pajak', 'Kas dan Setara Kas Pajak');

        $this->getJson('/api/v1/sikeu/akuntansi/coa')
            ->assertOk()
            ->assertJsonPath('data.0.nama_akun', 'Kas Tunai');

        $this->putJson('/api/v1/sikeu/pajak/pelaporan-akun/mode', [
            'mode' => 'publik',
        ])->assertOk()->assertJsonPath('data.mode', 'publik');

        $this->getJson('/api/v1/sikeu/akuntansi/coa')
            ->assertOk()
            ->assertJsonPath('data.0.nama_akun', 'Kas dan Setara Kas Pajak');
        $this->getJson('/api/v1/sikeu/akuntansi/jurnal')
            ->assertOk()
            ->assertJsonPath('data.0.details.0.akun.nama_akun', 'Kas dan Setara Kas Pajak');
        $this->getJson('/api/v1/sikeu/akuntansi/buku-besar?akun_id=' . $account->id)
            ->assertOk()
            ->assertJsonPath('data.0.akun.nama_akun', 'Kas dan Setara Kas Pajak');

        $this->assertDatabaseHas('sikeu_akun_keuangan', [
            'id' => $account->id,
            'nama_akun' => 'Kas Tunai',
        ]);
        $this->assertDatabaseHas('sikeu_detail_jurnal_umum', [
            'jurnal_id' => $journal->id,
            'akun_id' => $account->id,
            'debet' => 100,
            'kredit' => 0,
        ]);

        $this->putJson('/api/v1/sikeu/pajak/pelaporan-akun/mode', [
            'mode' => 'dev',
        ])->assertOk()->assertJsonPath('data.mode', 'dev');

        $this->getJson('/api/v1/sikeu/akuntansi/coa')
            ->assertOk()
            ->assertJsonPath('data.0.nama_akun', 'Kas Tunai');
        $this->getJson('/api/v1/sikeu/akuntansi/jurnal')
            ->assertOk()
            ->assertJsonPath('data.0.details.0.akun.nama_akun', 'Kas Tunai');
    }

    public function test_non_auditor_cannot_manage_tax_account_aliases(): void
    {
        $unauthorized = User::factory()->create();
        Passport::actingAs($unauthorized);

        $this->getJson('/api/v1/sikeu/pajak/pelaporan-akun')->assertForbidden();

        $account = AkunKeuangan::where('kode_akun', '101.01')->firstOrFail();
        $this->putJson("/api/v1/sikeu/pajak/pelaporan-akun/{$account->id}", [
            'nama_akun_pajak' => 'Tidak berizin',
        ])->assertForbidden();

        $this->assertDatabaseMissing('sikeu_akun_pajak', [
            'akun_id' => $account->id,
        ]);
    }
}
