<?php

namespace Tests\Feature;

use App\Models\Lms\ForumTopik;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadForumTest extends TestCase
{
    use RefreshDatabase;

    protected User $userDosen;
    protected User $userMhs;
    protected User $userLuar;
    protected Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->userDosen = User::factory()->create(['username' => 'dosen.forum', 'email' => 'dosen.forum@test.ac.id']);
        $this->userMhs = User::factory()->create(['username' => 'mhs.forum', 'email' => 'mhs.forum@test.ac.id']);
        $this->userLuar = User::factory()->create(['username' => 'mhs.forum.luar', 'email' => 'mhs.forum.luar@test.ac.id']);

        $prodi = ProgramStudi::create(['kode_prodi' => 'IF-FOR', 'nama' => 'Informatika Forum', 'jenjang' => 'S1']);
        $ta = TahunAkademik::create([
            'kode' => '20261', 'nama' => '2026/2027 Ganjil', 'semester' => 'ganjil',
            'tahun_mulai' => 2026, 'tahun_selesai' => 2027, 'is_aktif' => true,
        ]);
        $kurikulum = Kurikulum::create([
            'program_studi_id' => $prodi->id, 'kode' => 'KUR-FOR',
            'nama' => 'Kurikulum Forum', 'tahun_berlaku' => 2026, 'is_active' => true,
        ]);
        $mk = MataKuliah::create([
            'kurikulum_id' => $kurikulum->id, 'kode_mk' => 'IF601', 'nama' => 'MK Forum',
            'sks_teori' => 2, 'sks_praktik' => 1, 'total_sks' => 3, 'semester_anjuran' => 3, 'is_active' => true,
        ]);
        $dosen = Dosen::create([
            'user_id' => $this->userDosen->id, 'program_studi_id' => $prodi->id,
            'nidn' => '0066006600', 'nama_lengkap' => 'Dosen Forum', 'status_aktif' => 'aktif',
        ]);
        $mahasiswa = Mahasiswa::create([
            'user_id' => $this->userMhs->id, 'program_studi_id' => $prodi->id,
            'nim' => '2026006001', 'nama_lengkap' => 'Mahasiswa Forum', 'angkatan' => 2026, 'status_akademik' => 'aktif',
        ]);
        Mahasiswa::create([
            'user_id' => $this->userLuar->id, 'program_studi_id' => $prodi->id,
            'nim' => '2026006002', 'nama_lengkap' => 'Mahasiswa Luar Forum', 'angkatan' => 2026, 'status_akademik' => 'aktif',
        ]);

        $this->kelas = Kelas::create([
            'program_studi_id' => $prodi->id, 'mata_kuliah_id' => $mk->id,
            'tahun_akademik_id' => $ta->id, 'kode_kelas' => 'IF-FOR-A',
            'nama_kelas' => 'Kelas Forum A', 'kapasitas' => 30,
        ]);
        DosenPengampu::create(['kelas_id' => $this->kelas->id, 'dosen_id' => $dosen->id, 'peran' => 'pengampu_utama']);

        $krs = Krs::create([
            'mahasiswa_id' => $mahasiswa->id, 'tahun_akademik_id' => $ta->id,
            'status' => 'disetujui', 'total_sks' => 3,
        ]);
        KrsDetail::create(['krs_id' => $krs->id, 'kelas_id' => $this->kelas->id, 'status' => 'aktif']);

        $roleDosen = Role::firstOrCreate(['slug' => 'dosen_pengajar'], ['name' => 'Dosen Pengajar']);
        $roleMhs = Role::firstOrCreate(['slug' => 'mahasiswa'], ['name' => 'Mahasiswa']);
        $perms = [];
        foreach ([
            'siakad.kelas.read' => 'read',
            'siakad.kelas.manage' => 'update',
            'siakad.krs.read' => 'read',
            'lms.forum.read' => 'read',
            'lms.forum.create' => 'create',
            'lms.forum.manage' => 'update',
        ] as $slug => $act) {
            $perms[$slug] = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'module' => str_contains($slug, '.') ? explode('.', $slug)[0] : 'siakad', 'action' => $act]
            );
        }
        $roleDosen->permissions()->syncWithoutDetaching([
            $perms['siakad.kelas.read']->id,
            $perms['siakad.kelas.manage']->id,
            $perms['lms.forum.read']->id,
            $perms['lms.forum.create']->id,
            $perms['lms.forum.manage']->id,
        ]);
        // Mahasiswa hanya boleh berdiskusi: read + create, tanpa manage.
        $roleMhs->permissions()->syncWithoutDetaching([
            $perms['siakad.kelas.read']->id,
            $perms['siakad.krs.read']->id,
            $perms['lms.forum.read']->id,
            $perms['lms.forum.create']->id,
        ]);
        $this->userDosen->roles()->attach($roleDosen->id);
        $this->userMhs->roles()->attach($roleMhs->id);
        $this->userLuar->roles()->attach($roleMhs->id);
    }

    public function test_forum_topik_otomatis_post_balasan_dan_hak_akses(): void
    {
        // 1. Mahasiswa buka forum → Diskusi Umum otomatis dibuat
        Passport::actingAs($this->userMhs);
        $list = $this->getJson("/api/v1/lms/kelas/{$this->kelas->id}/forum");
        $list->assertStatus(200)->assertJsonCount(1, 'data');
        $topikId = $list->json('data.0.id');
        $this->assertStringContainsString('Diskusi Umum', $list->json('data.0.judul'));

        // 2. Mahasiswa kirim pesan
        $post = $this->postJson("/api/v1/lms/forum/{$topikId}/post", ['isi' => 'Apakah UTS open book?']);
        $post->assertStatus(201)->assertJsonPath('data.nama_penulis', 'Mahasiswa Forum');
        $postId = $post->json('data.id');

        // 3. Dosen balas (1 level)
        Passport::actingAs($this->userDosen);
        $this->postJson("/api/v1/lms/forum/{$topikId}/post", ['isi' => 'Ya, open book.', 'parent_id' => $postId])
            ->assertStatus(201);

        // Balasan atas balasan ditolak (hanya 1 level)
        $replyId = \App\Models\Lms\ForumPost::where('parent_id', $postId)->first()->id;
        $this->postJson("/api/v1/lms/forum/{$topikId}/post", ['isi' => 'Level 3?', 'parent_id' => $replyId])
            ->assertStatus(422);

        // 4. List pesan tampil nested
        $posts = $this->getJson("/api/v1/lms/forum/{$topikId}/post");
        $posts->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.balasan');

        // 5. Mahasiswa luar kelas ditolak
        Passport::actingAs($this->userLuar);
        $this->getJson("/api/v1/lms/kelas/{$this->kelas->id}/forum")->assertStatus(422);
        $this->postJson("/api/v1/lms/forum/{$topikId}/post", ['isi' => 'Spam'])->assertStatus(422);

        // 6. Mahasiswa tidak bisa hapus pesan dosen; dosen bisa hapus pesan siapa pun
        Passport::actingAs($this->userMhs);
        // Mahasiswa tidak boleh menghapus pesan orang lain -> 403 (Forbidden)
        $this->deleteJson("/api/v1/lms/forum-post/{$replyId}")->assertStatus(403);
        Passport::actingAs($this->userDosen);
        $this->deleteJson("/api/v1/lms/forum-post/{$postId}")->assertStatus(200);
        $this->assertDatabaseMissing('lms_forum_post', ['id' => $postId]);
    }

    public function test_dosen_kelola_topik(): void
    {
        Passport::actingAs($this->userDosen);

        // List dulu memicu auto-create Diskusi Umum
        $this->getJson("/api/v1/lms/kelas/{$this->kelas->id}/forum")->assertStatus(200);

        $this->postJson("/api/v1/lms/kelas/{$this->kelas->id}/forum", ['judul' => 'Tanya Jawab UAS', 'is_pinned' => true])
            ->assertStatus(201);

        $list = $this->getJson("/api/v1/lms/kelas/{$this->kelas->id}/forum");
        $list->assertStatus(200)->assertJsonCount(2, 'data');
        // Pinned teratas
        $this->assertTrue($list->json('data.0.is_pinned'));

        $topikId = $list->json('data.0.id');
        $this->deleteJson("/api/v1/lms/forum/{$topikId}")->assertStatus(200);

        // Mahasiswa tidak boleh buat topik (butuh lms.forum.manage)
        Passport::actingAs($this->userMhs);
        $this->postJson("/api/v1/lms/kelas/{$this->kelas->id}/forum", ['judul' => 'Spam Topik'])
            ->assertStatus(403);
    }

    public function test_mahasiswa_tidak_moderasi_pesan_orang_lain(): void
    {
        $this->assertFalse(
            $this->userMhs->hasPermission('lms.forum.manage'),
            'Mahasiswa tidak boleh punya permission moderasi forum.'
        );

        // Mahasiswa tetap boleh menghapus pesan miliknya sendiri.
        Passport::actingAs($this->userMhs);
        $list = $this->getJson("/api/v1/lms/kelas/{$this->kelas->id}/forum");
        $topikId = $list->json('data.0.id');
        $postId = $this->postJson("/api/v1/lms/forum/{$topikId}/post", ['isi' => 'Catatan saya'])
            ->json('data.id');

        $this->deleteJson("/api/v1/lms/forum-post/{$postId}")->assertStatus(200);
        $this->assertDatabaseMissing('lms_forum_post', ['id' => $postId]);
    }

    public function test_permission_forum_terpisah_dari_siakad(): void
    {
        // Permission forum harus entitlement sendiri, bukan pinjam dari SIAKAD.
        $this->assertTrue($this->userMhs->hasPermission('lms.forum.read'));
        $this->assertTrue($this->userMhs->hasPermission('lms.forum.create'));
        $this->assertFalse($this->userMhs->hasPermission('lms.forum.manage'));
    }

    public function test_pembaca_forum_tidak_boleh_menulis_pesan(): void
    {
        // Guard route memakai `lms.forum.create`; role yang hanya boleh membaca
        // harus tertahan di middleware, bukan lolos ke controller.
        $roleBaca = Role::firstOrCreate(['slug' => 'lms_forum_readers'], ['name' => 'Pembaca Forum']);
        $roleBaca->permissions()->syncWithoutDetaching([
            Permission::where('slug', 'lms.forum.read')->value('id'),
        ]);

        $userBaca = User::factory()->create(['username' => 'mhs.forum.readonly']);
        Mahasiswa::create([
            'user_id' => $userBaca->id,
            'program_studi_id' => $this->kelas->program_studi_id,
            'nim' => '2026006009',
            'nama_lengkap' => 'Mahasiswa Read Only',
            'angkatan' => 2026,
            'status_akademik' => 'aktif',
        ]);
        $userBaca->roles()->attach($roleBaca->id);

        $krs = Krs::create([
            'mahasiswa_id' => Mahasiswa::where('user_id', $userBaca->id)->value('id'),
            'tahun_akademik_id' => $this->kelas->tahun_akademik_id,
            'status' => 'disetujui',
            'total_sks' => 3,
        ]);
        KrsDetail::create(['krs_id' => $krs->id, 'kelas_id' => $this->kelas->id, 'status' => 'aktif']);

        Passport::actingAs($userBaca);

        $topikId = $this->getJson("/api/v1/lms/kelas/{$this->kelas->id}/forum")
            ->assertStatus(200)
            ->json('data.0.id');

        $this->postJson("/api/v1/lms/forum/{$topikId}/post", ['isi' => 'Harus ditolak'])
            ->assertStatus(403);
    }

    public function test_sorting_dan_meta_filters(): void
    {
        Passport::actingAs($this->userDosen);

        $list = $this->getJson("/api/v1/lms/kelas/{$this->kelas->id}/forum");
        $topikId = $list->json('data.0.id');

        $this->postJson("/api/v1/lms/kelas/{$this->kelas->id}/forum", ['judul' => 'Topik Lama'])->assertStatus(201);

        // Default: id ASC → topik paling lama lebih dulu.
        $asc = $this->getJson("/api/v1/lms/kelas/{$this->kelas->id}/forum?sort_by=id&sort_order=asc");
        $asc->assertStatus(200)
            ->assertJsonPath('meta.filters.sort_order', 'asc')
            ->assertJsonPath('meta.filters.sort_by', 'id');
        $this->assertSame(
            ['Diskusi Umum Kelas MK Forum', 'Topik Lama'],
            collect($asc->json('data'))->pluck('judul')->all()
        );

        // sort_order=desc → kebalikan.
        $desc = $this->getJson("/api/v1/lms/kelas/{$this->kelas->id}/forum?sort_by=id&sort_order=desc");
        $this->assertSame(
            ['Topik Lama', 'Diskusi Umum Kelas MK Forum'],
            collect($desc->json('data'))->pluck('judul')->all()
        );

        // sort_by di luar whitelist diabaikan, tidak error, dan filters tetap sinkron.
        $asing = $this->getJson("/api/v1/lms/kelas/{$this->kelas->id}/forum?sort_by=password");
        $asing->assertStatus(200)->assertJsonPath('meta.filters.sort_by', 'id');

        // Tiga pesan untuk menguji sorting daftar pesan.
        foreach (['Satu', 'Dua', 'Tiga'] as $isi) {
            $this->postJson("/api/v1/lms/forum/{$topikId}/post", ['isi' => $isi])->assertStatus(201);
        }

        $postAsc = $this->getJson("/api/v1/lms/forum/{$topikId}/post?sort_order=asc");
        $postAsc->assertStatus(200)->assertJsonPath('meta.filters.sort_order', 'asc');
        $this->assertSame(['Satu', 'Dua', 'Tiga'], collect($postAsc->json('data'))->pluck('isi')->all());

        $postDesc = $this->getJson("/api/v1/lms/forum/{$topikId}/post?sort_order=desc");
        $this->assertSame(['Tiga', 'Dua', 'Satu'], collect($postDesc->json('data'))->pluck('isi')->all());
    }

    public function test_filter_kelas_tidak_membocorkan_kelas_lain(): void
    {
        Passport::actingAs($this->userDosen);

        // Topik pada kelas yang diampu dosen ini.
        $this->getJson("/api/v1/lms/kelas/{$this->kelas->id}/forum")->assertStatus(200);

        // Kelas kedua yang TIDAK diampu dosen ini (dibuat tanpa DosenPengampu).
        $kelasLain = Kelas::create([
            'program_studi_id' => $this->kelas->program_studi_id,
            'mata_kuliah_id' => $this->kelas->mata_kuliah_id,
            'tahun_akademik_id' => $this->kelas->tahun_akademik_id,
            'kode_kelas' => 'IF-FOR-B',
            'nama_kelas' => 'Kelas Forum B',
            'kapasitas' => 30,
        ]);
        // Topik di kelas lain dibuat langsung lewat model: fokus test ini adalah
        // irisan filter kelas_id dengan hak akses, bukan alur otorisasi admin.
        ForumTopik::create([
            'kelas_id' => $kelasLain->id,
            'judul' => 'Topik Kelas B',
            'dibuat_oleh' => $this->userDosen->id,
        ]);

        // Dosen hanya melihat topiknya sendiri pada kelas yang diampu.
        Passport::actingAs($this->userDosen);
        $semua = $this->getJson('/api/v1/lms/forum');
        $semua->assertStatus(200)->assertJsonPath('meta.total', 1);
        $this->assertSame([$this->kelas->id], collect($semua->json('data'))->pluck('kelas_id')->unique()->all());

        // Filter kelas sendiri tetap mengembalikan topiknya.
        $pilihSendiri = $this->getJson("/api/v1/lms/forum?kelas_id={$this->kelas->id}");
        $pilihSendiri->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.filters.kelas_id', $this->kelas->id);

        // Filter kelas yang tidak diakses → kosong, bukan error 403 dan bukan data.
        $pilihAsing = $this->getJson("/api/v1/lms/forum?kelas_id={$kelasLain->id}");
        $pilihAsing->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_filter_status_sematkan_topik(): void
    {
        Passport::actingAs($this->userDosen);

        $res = $this->postJson("/api/v1/lms/kelas/{$this->kelas->id}/forum", [
            'judul' => 'Topik Disematkan',
            'is_pinned' => true,
        ])->assertStatus(201);
        $topikId = $res->json('data.id');

        $this->postJson("/api/v1/lms/kelas/{$this->kelas->id}/forum", [
            'judul' => 'Topik Biasa',
        ])->assertStatus(201);

        // Tanpa filter: semua topik, sematan tetap didahulukan.
        $semua = $this->getJson('/api/v1/lms/forum');
        $semua->assertStatus(200)
            ->assertJsonPath('meta.filters.is_pinned', null)
            ->assertJsonPath('meta.total', 2);
        $this->assertSame($topikId, $semua->json('data.0.id'));

        // true → hanya topik bersemat.
        $ya = $this->getJson('/api/v1/lms/forum?is_pinned=true');
        $ya->assertStatus(200)
            ->assertJsonPath('meta.filters.is_pinned', true)
            ->assertJsonCount(1, 'data');
        $this->assertSame([$topikId], collect($ya->json('data'))->pluck('id')->all());

        // false → hanya topik yang tidak disematkan.
        $tidak = $this->getJson('/api/v1/lms/forum?is_pinned=0');
        $tidak->assertStatus(200)
            ->assertJsonPath('meta.filters.is_pinned', false)
            ->assertJsonCount(1, 'data');
        $this->assertFalse($tidak->json('data.0.is_pinned'));

        // Nilai tak dikenal diabaikan diam-diam (= semua), bukan 422 dan bukan false.
        $ngawur = $this->getJson('/api/v1/lms/forum?is_pinned=ngawur');
        $ngawur->assertStatus(200)
            ->assertJsonPath('meta.filters.is_pinned', null)
            ->assertJsonPath('meta.total', 2);
    }
}
