<?php

namespace Tests\Feature;

use App\Mail\KwitansiPembayaranMail;
use App\Models\Siakad\Mahasiswa;
use App\Models\Sikeu\DispensasiTagihan;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Spmb\HasilSeleksi;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\User;
use App\Services\Sikeu\KwitansiPembayaranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SikeuCompatTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Mahasiswa $mahasiswa;
    protected int $programStudiId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\Sikeu\SikeuAkuntansiSeeder::class);

        if (\Laravel\Passport\Client::where('personal_access_client', 1)->doesntExist()) {
            app(\Laravel\Passport\ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
        }

        $this->admin = User::firstOrCreate(
            ['email' => 'admin.compat@kampus.ac.id'],
            ['username' => 'compatadmin', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );

        $prodi = \App\Models\Siakad\ProgramStudi::create([
            'kode_prodi' => 'CMP' . rand(100, 999),
            'nama' => 'Prodi Kompat Test',
            'jenjang' => 'D3',
            'is_active' => true,
        ]);
        $this->programStudiId = $prodi->id;

        $this->mahasiswa = Mahasiswa::create([
            'program_studi_id' => $prodi->id,
            'nim' => 'CMP' . rand(10000, 99999),
            'nama_lengkap' => 'Mhs Kompat',
            'email' => 'mhskompat@kampus.ac.id',
            'angkatan' => 2024,
            'status' => 'aktif',
        ]);
    }

    protected function headers(): array
    {
        $result = $this->admin->createToken('compat-token');
        $token = $result->plainTextToken ?? $result->accessToken;

        return ['Authorization' => 'Bearer ' . $token];
    }

    protected function buatTagihan(float $total = 3000000, float $bayar = 0, string $status = 'belum_bayar'): TagihanMahasiswa
    {
        return TagihanMahasiswa::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => 1,
            'nomor_tagihan' => 'INV-CMP-' . uniqid(),
            'total_tagihan' => $total,
            'total_potongan' => 0,
            'total_denda' => 0,
            'total_bayar' => $bayar,
            'status' => $status,
            'jatuh_tempo' => now()->addDays(30),
        ]);
    }

    public function test_ringkasan_butuh_identitas_dan_404_bila_tak_ada()
    {
        $res = $this->withHeaders($this->headers())->getJson('/api/v1/sikeu/compat/ringkasan-pembayaran');
        $res->assertStatus(422);

        $res = $this->withHeaders($this->headers())->getJson('/api/v1/sikeu/compat/ringkasan-pembayaran?nim=TIDAKADA');
        $res->assertStatus(404);

        $this->buatTagihan(3000000, 1000000, 'sebagian');
        $res = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/compat/ringkasan-pembayaran?mahasiswa_id=' . $this->mahasiswa->id);
        $res->assertStatus(200)->assertJsonPath('status', 'success');
        $this->assertEquals(2000000, $res->json('data.sisa'));
        $this->assertFalse($res->json('data.lunas'));
    }

    public function test_eligibilitas_ujian_lunas_vs_tunggakan_vs_bypass()
    {
        // Lunas -> eligible
        $this->buatTagihan(1000000, 1000000, 'lunas');
        $res = $this->withHeaders($this->headers())->getJson(
            '/api/v1/sikeu/compat/eligibilitas-ujian?mahasiswa_id=' . $this->mahasiswa->id . '&tipe_ujian=UTS'
        );
        $res->assertStatus(200)->assertJsonPath('data.eligible', true);

        // Tunggakan -> tidak eligible
        $tagihanAktif = $this->buatTagihan(3000000, 0, 'belum_bayar');
        $res = $this->withHeaders($this->headers())->getJson(
            '/api/v1/sikeu/compat/eligibilitas-ujian?mahasiswa_id=' . $this->mahasiswa->id . '&tipe_ujian=UAS'
        );
        $res->assertStatus(200)->assertJsonPath('data.eligible', false);

        // Bypass dispensasi -> eligible
        DispensasiTagihan::create([
            'tagihan_id' => $tagihanAktif->id,
            'mahasiswa_id' => $this->mahasiswa->id,
            'tipe_dispensasi' => 'penundaan_jatuh_tempo',
            'alasan' => 'Ujian test',
            'status' => 'approved',
            'allow_krs' => true,
        ]);
        $res = $this->withHeaders($this->headers())->getJson(
            '/api/v1/sikeu/compat/eligibilitas-ujian?mahasiswa_id=' . $this->mahasiswa->id . '&tipe_ujian=ALL'
        );
        $res->assertStatus(200)->assertJsonPath('data.eligible', true);
        $res->assertJsonPath('data.bypass_dispensasi', true);

        // tipe_ujian invalid -> 422
        $res = $this->withHeaders($this->headers())->getJson(
            '/api/v1/sikeu/compat/eligibilitas-ujian?mahasiswa_id=' . $this->mahasiswa->id . '&tipe_ujian=QUIZ'
        );
        $res->assertStatus(422);
    }

    public function test_kwitansi_email_terkirim_bila_ada_alamat()
    {
        Mail::fake();
        $tagihan = $this->buatTagihan(1000000, 1000000, 'lunas');
        $pembayaran = Pembayaran::create([
            'tagihan_id' => $tagihan->id,
            'kode_transaksi' => 'TRX-MAIL-' . uniqid(),
            'jumlah_bayar' => 1000000,
            'waktu_bayar' => now(),
            'channel_bayar' => 'LOKET_TUNAI',
            'status' => 'success',
        ]);

        KwitansiPembayaranService::kirimSatuan($pembayaran, 0);
        Mail::assertSent(KwitansiPembayaranMail::class);
    }

    public function test_kwitansi_email_dilewati_bila_tanpa_alamat()
    {
        Mail::fake();
        $mhs = Mahasiswa::create([
            'program_studi_id' => $this->programStudiId,
            'nim' => 'NOEM' . rand(10000, 99999),
            'nama_lengkap' => 'Tanpa Email',
            'angkatan' => 2024,
            'status' => 'aktif',
        ]);
        $tagihan = TagihanMahasiswa::create([
            'mahasiswa_id' => $mhs->id,
            'tahun_akademik_id' => 1,
            'nomor_tagihan' => 'INV-NOEM-' . uniqid(),
            'total_tagihan' => 500000,
            'total_potongan' => 0,
            'total_denda' => 0,
            'total_bayar' => 500000,
            'status' => 'lunas',
            'jatuh_tempo' => now()->addDays(30),
        ]);
        $pembayaran = Pembayaran::create([
            'tagihan_id' => $tagihan->id,
            'kode_transaksi' => 'TRX-NOEM-' . uniqid(),
            'jumlah_bayar' => 500000,
            'waktu_bayar' => now(),
            'channel_bayar' => 'LOKET_TUNAI',
            'status' => 'success',
        ]);

        KwitansiPembayaranService::kirimSatuan($pembayaran, 0);
        Mail::assertNothingSent();
    }
}
