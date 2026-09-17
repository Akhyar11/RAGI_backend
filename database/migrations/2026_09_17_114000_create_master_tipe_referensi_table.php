<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Buat tabel core_tipe_referensi jika belum ada
        if (!Schema::hasTable('core_tipe_referensi')) {
            Schema::create('core_tipe_referensi', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 50)->unique();
                $table->string('nama', 100);
                $table->string('modul', 50)->default('global');
                $table->text('deskripsi')->nullable();
                $table->integer('urutan')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['modul', 'is_active']);
                $table->index('kode');
            });
        }

        // 2. Initial Seed Daftar Tipe Referensi Standar Kampus
        $standardTypes = [
            [
                'kode' => 'agama',
                'nama' => 'Agama',
                'modul' => 'global',
                'deskripsi' => 'Daftar agama resmi yang diakui dan digunakan di seluruh sistem kampus.',
                'urutan' => 1,
            ],
            [
                'kode' => 'status_sipil',
                'nama' => 'Status Sipil / Pernikahan',
                'modul' => 'global',
                'deskripsi' => 'Status perkawinan/sipil mahasiswa atau pegawai (Belum Menikah, Menikah, Cerai).',
                'urutan' => 2,
            ],
            [
                'kode' => 'kewarganegaraan',
                'nama' => 'Kewarganegaraan',
                'modul' => 'global',
                'deskripsi' => 'Status kewarganegaraan (WNI, WNA).',
                'urutan' => 3,
            ],
            [
                'kode' => 'golongan_darah',
                'nama' => 'Golongan Darah',
                'modul' => 'global',
                'deskripsi' => 'Golongan darah civitas akademika (A, B, AB, O).',
                'urutan' => 4,
            ],
            [
                'kode' => 'jenis_tinggal',
                'nama' => 'Jenis Tempat Tinggal',
                'modul' => 'global',
                'deskripsi' => 'Jenis tempat tinggal (Bersama Orang Tua, Kost, Asrama, dll).',
                'urutan' => 5,
            ],
            [
                'kode' => 'alat_transportasi',
                'nama' => 'Alat Transportasi',
                'modul' => 'global',
                'deskripsi' => 'Moda transportasi ke kampus (Sepeda Motor, Mobil, Angkutan Umum, Jalan Kaki).',
                'urutan' => 6,
            ],
            [
                'kode' => 'pendidikan_ortu',
                'nama' => 'Pendidikan Orang Tua / Wali',
                'modul' => 'global',
                'deskripsi' => 'Jenjang pendidikan terakhir orang tua atau wali.',
                'urutan' => 7,
            ],
            [
                'kode' => 'pekerjaan_ortu',
                'nama' => 'Pekerjaan Orang Tua / Wali',
                'modul' => 'global',
                'deskripsi' => 'Profesi atau bidang pekerjaan orang tua / wali.',
                'urutan' => 8,
            ],
            [
                'kode' => 'penghasilan_ortu',
                'nama' => 'Penghasilan Orang Tua / Wali',
                'modul' => 'spmb',
                'deskripsi' => 'Rentang penghasilan bulanan orang tua/wali untuk evaluasi UKT/Beasiswa.',
                'urutan' => 9,
            ],
            [
                'kode' => 'asal_lulusan',
                'nama' => 'Asal Sekolah / Lulusan',
                'modul' => 'spmb',
                'deskripsi' => 'Asal jenjang sekolah pendaftar (SMA, SMK, MA, Pesantren, Paket C).',
                'urutan' => 10,
            ],
            [
                'kode' => 'info_daftar',
                'nama' => 'Sumber Informasi Pendaftaran',
                'modul' => 'spmb',
                'deskripsi' => 'Saluran pendaftar mengetahui informasi kampus (Brosur, Medsos, Kerabat, dll).',
                'urutan' => 11,
            ],
            [
                'kode' => 'jenis_pt',
                'nama' => 'Jenis Perguruan Tinggi',
                'modul' => 'spmb',
                'deskripsi' => 'Klasifikasi perguruan tinggi asal untuk pendaftar transfer / alih jenjang.',
                'urutan' => 12,
            ],
            [
                'kode' => 'jenjang_pt',
                'nama' => 'Jenjang Pendidikan Perguruan Tinggi',
                'modul' => 'spmb',
                'deskripsi' => 'Jenjang pendidikan akademik/vokasi (D3, D4, S1, S2, Profesi).',
                'urutan' => 13,
            ],
        ];

        $now = now();
        foreach ($standardTypes as $st) {
            DB::table('core_tipe_referensi')->updateOrInsert(
                ['kode' => $st['kode']],
                [
                    'nama' => $st['nama'],
                    'modul' => $st['modul'],
                    'deskripsi' => $st['deskripsi'],
                    'urutan' => $st['urutan'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        // 3. Masukkan tipe lain yang saat ini sudah ada di spmb_master_referensi jika belum terdaftar
        if (Schema::hasTable('spmb_master_referensi')) {
            $existingTipes = DB::table('spmb_master_referensi')
                ->select('tipe', 'modul')
                ->distinct()
                ->get();

            foreach ($existingTipes as $item) {
                if (!empty($item->tipe)) {
                    $exists = DB::table('core_tipe_referensi')->where('kode', $item->tipe)->exists();
                    if (!$exists) {
                        $namaFormatted = ucwords(str_replace('_', ' ', $item->tipe));
                        DB::table('core_tipe_referensi')->insert([
                            'kode' => $item->tipe,
                            'nama' => $namaFormatted,
                            'modul' => $item->modul ?: 'global',
                            'deskripsi' => "Kategori referensi {$namaFormatted}",
                            'urutan' => 99,
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }

        // 4. Daftarkan menu Master Tipe Referensi di core_menus jika belum ada
        if (Schema::hasTable('core_menus')) {
            $parentIam = DB::table('core_menus')->where('url', '#iam_section')->first();
            $parentId = $parentIam ? $parentIam->id : 2;

            $menuData = [
                'name' => 'Master Tipe Referensi',
                'url' => '/admin/master-tipe-referensi',
                'icon' => 'FaTags',
                'module' => 'sso',
                'parent_id' => $parentId,
                'order_index' => 22,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $menuId = DB::table('core_menus')->where('url', '/admin/master-tipe-referensi')->value('id');
            if (!$menuId) {
                $menuId = DB::table('core_menus')->insertGetId($menuData);
            }

            // Berikan hak akses default ke role 1 (Super Administrator)
            if ($menuId && Schema::hasTable('core_menu_role')) {
                $existsInRole = DB::table('core_menu_role')
                    ->where('menu_id', $menuId)
                    ->where('role_id', 1)
                    ->exists();

                if (!$existsInRole) {
                    DB::table('core_menu_role')->insert([
                        'menu_id' => $menuId,
                        'role_id' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('core_menus')) {
            $menuId = DB::table('core_menus')->where('url', '/admin/master-tipe-referensi')->value('id');
            if ($menuId && Schema::hasTable('core_menu_role')) {
                DB::table('core_menu_role')->where('menu_id', $menuId)->delete();
            }
            DB::table('core_menus')->where('url', '/admin/master-tipe-referensi')->delete();
        }

        Schema::dropIfExists('core_tipe_referensi');
    }
};
