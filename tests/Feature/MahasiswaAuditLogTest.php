<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Siakad\Mahasiswa;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MahasiswaAuditLogTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
    }

    public function test_mahasiswa_observer_records_audit_log_on_crud()
    {
        // Login as admin
        $admin = User::factory()->create();
        $this->actingAs($admin, 'api');

        // Test Create
        $mahasiswa = Mahasiswa::create([
            'nama_lengkap' => 'Ahmad Fadhil',
            'nim' => '2201001999',
            'nik' => '3201012345679999',
            'status' => 'aktif',
            'angkatan' => 2024,
            'program_studi_id' => 1
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'SIAKAD',
            'action' => 'create',
            'table_name' => 'siakad_mahasiswa',
            'record_id' => $mahasiswa->id,
            'user_id' => $admin->id
        ]);

        // Test Update
        $mahasiswa->update([
            'nama_lengkap' => 'Ahmad Fadhil Updated'
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'SIAKAD',
            'action' => 'update',
            'table_name' => 'siakad_mahasiswa',
            'record_id' => $mahasiswa->id,
            'user_id' => $admin->id
        ]);

        // Test Delete
        $mahasiswa->delete();

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'SIAKAD',
            'action' => 'delete',
            'table_name' => 'siakad_mahasiswa',
            'record_id' => $mahasiswa->id,
            'user_id' => $admin->id
        ]);
    }
}
