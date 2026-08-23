<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MassRenameTables extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:mass-rename';

    /**
     * The console command description.
     */
    protected $description = 'Mass rename tables across migrations and models to enforce module prefixes.';

    /**
     * Map of old table names to new table names.
     */
    protected $tableMap = [
        // SIKEU
        'jenis_biaya' => 'sikeu_jenis_biaya',
        'tagihan_mahasiswa' => 'sikeu_tagihan_mahasiswa',
        'detail_tagihan' => 'sikeu_detail_tagihan',
        'potongan_tagihan' => 'sikeu_potongan_tagihan',
        'dispensasi_tagihan' => 'sikeu_dispensasi_tagihan',
        'pemasukan_kampus' => 'sikeu_pemasukan_kampus',
        'akun_keuangan' => 'sikeu_akun_keuangan',
        'jurnal_umum' => 'sikeu_jurnal_umum',
        'detail_jurnal_umum' => 'sikeu_detail_jurnal_umum',
        'pengajuan_pencairan_kas' => 'sikeu_pengajuan_pencairan_kas',
        'approval_history_pencairan' => 'sikeu_approval_history_pencairan',
        'pengeluaran_kampus' => 'sikeu_pengeluaran_kampus',
        'payment_gateway_configs' => 'sikeu_payment_gateway_configs',
        'mahasiswa_tipe_tagihan' => 'sikeu_mahasiswa_tipe_tagihan',
        'tarif_spmb' => 'sikeu_tarif_spmb',
        'callback_payment_gateway' => 'sikeu_callback_payment_gateway',
        'denda_tagihan' => 'sikeu_denda_tagihan',
        'laporan_bukti_pelaksanaan' => 'sikeu_laporan_bukti_pelaksanaan',
        'pembayaran' => 'sikeu_pembayaran',
        'periode_akuntansi' => 'sikeu_periode_akuntansi',
        'rekonsiliasi_pembayaran' => 'sikeu_rekonsiliasi_pembayaran',
        'transaksi_kas_unit' => 'sikeu_transaksi_kas_unit',
        'unit_kas' => 'sikeu_unit_kas',
        'virtual_account' => 'sikeu_virtual_account',

        // SPMB
        'jalur_masuk' => 'spmb_jalur_masuk',
        'gelombang_penerimaan' => 'spmb_gelombang_penerimaan',
        'pendaftaran_calon_mhs' => 'spmb_pendaftaran_calon_mhs',
        'dokumen_pendaftaran' => 'spmb_dokumen_pendaftaran',
        'pembayaran_spmb' => 'spmb_pembayaran',
        'kuesioner_spmb' => 'spmb_kuesioner',
        'pertanyaan_kuesioner_spmb' => 'spmb_pertanyaan_kuesioner',
        'jawaban_kuesioner_spmb' => 'spmb_jawaban_kuesioner',
        'nilai_seleksi' => 'spmb_nilai_seleksi',
        'hasil_seleksi' => 'spmb_hasil_seleksi',
        'konversi_mahasiswa' => 'spmb_konversi_mahasiswa',
        'pengumuman_spmb' => 'spmb_pengumuman',
        'master_program_studi' => 'spmb_master_program_studi',
        'master_tahun_akademik' => 'spmb_master_tahun_akademik',
        'tarif_ukt_spmb' => 'spmb_tarif_ukt',
        'berkas_requirement' => 'spmb_berkas_requirement',
        'master_referensi' => 'spmb_master_referensi',
        'master_tipe_jalur_alur' => 'spmb_master_tipe_jalur_alur',

        // SIPPM
        'skema_kegiatan' => 'sippm_skema_kegiatan',
        'periode_hibah' => 'sippm_periode_hibah',
        'proposal_kegiatan' => 'sippm_proposal_kegiatan',
        'anggota_kegiatan' => 'sippm_anggota_kegiatan',
        'reviewer_kegiatan' => 'sippm_reviewer_kegiatan',
        'penilaian_proposal' => 'sippm_penilaian_proposal',
        'kontrak_kegiatan' => 'sippm_kontrak_kegiatan',
        'pencairan_dana_hibah' => 'sippm_pencairan_dana_hibah',
        'laporan_kegiatan' => 'sippm_laporan_kegiatan',
        'publikasi_ilmiah' => 'sippm_publikasi_ilmiah',
        'hki_dan_buku' => 'sippm_hki_dan_buku',
        'standar_iku5_prodi' => 'sippm_standar_iku5_prodi',
        'pengumuman_hibah' => 'sippm_pengumuman_hibah',

        // SIMPEG
        'unit_kerja' => 'simpeg_unit_kerja',
        'jabatan' => 'simpeg_jabatan',
        'jabatan_fungsional_akademik' => 'simpeg_jabatan_fungsional_akademik',
        'pegawai' => 'simpeg_pegawai',
        'riwayat_jabatan' => 'simpeg_riwayat_jabatan',
        'riwayat_pendidikan_pegawai' => 'simpeg_riwayat_pendidikan_pegawai',
        'dokumen_pegawai' => 'simpeg_dokumen_pegawai',
        'pengajuan_cuti' => 'simpeg_pengajuan_cuti',
        'presensi_pegawai' => 'simpeg_presensi_pegawai',
        'gaji_pegawai' => 'simpeg_gaji_pegawai',
        'usulan_jafung' => 'simpeg_usulan_jafung',
        'penilaian_kinerja' => 'simpeg_penilaian_kinerja',

        // SINAPRA
        'gedung' => 'sinapra_gedung',
        'ruangan' => 'sinapra_ruangan',
        'kategori_aset' => 'sinapra_kategori_aset',
        'aset' => 'sinapra_aset',
        'peminjaman_ruangan' => 'sinapra_peminjaman_ruangan',
        'peminjaman_aset' => 'sinapra_peminjaman_aset',
        'maintenance_log' => 'sinapra_maintenance_log',
        'pengajuan_pengadaan' => 'sinapra_pengajuan_pengadaan',
        'detail_pengadaan' => 'sinapra_detail_pengadaan',

        // CORE/IAM
        'users' => 'core_users',
        'roles' => 'core_roles',
        'permissions' => 'core_permissions',
        'user_roles' => 'core_user_roles',
        'role_permissions' => 'core_role_permissions',
        'audit_logs' => 'core_audit_logs',
        'sso_tokens' => 'core_sso_tokens',
        'user_sessions_iam' => 'core_user_sessions_iam',
        'menus' => 'core_menus',
        'menu_role' => 'core_menu_role',
        'modules' => 'core_modules',
        'system_settings' => 'core_system_settings',
        'jenis_biaya_modules' => 'core_jenis_biaya_modules',
        'master_tipe_jalur' => 'core_master_tipe_jalur',
        'master_jalur_kelas' => 'core_master_jalur_kelas',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting mass rename of tables in codebase...');

        // 1. Update Migrations
        $migrationFiles = File::files(database_path('migrations'));
        foreach ($migrationFiles as $file) {
            $content = File::get($file);
            $newContent = $content;

            foreach ($this->tableMap as $old => $new) {
                // Schema::create('old_name', ...)
                $newContent = preg_replace("/Schema::create\(\s*['\"]{$old}['\"]/i", "Schema::create('{$new}'", $newContent);
                // Schema::table('old_name', ...)
                $newContent = preg_replace("/Schema::table\(\s*['\"]{$old}['\"]/i", "Schema::table('{$new}'", $newContent);
                // Schema::dropIfExists('old_name')
                $newContent = preg_replace("/Schema::dropIfExists\(\s*['\"]{$old}['\"]/i", "Schema::dropIfExists('{$new}'", $newContent);
                // Schema::hasTable('old_name')
                $newContent = preg_replace("/Schema::hasTable\(\s*['\"]{$old}['\"]/i", "Schema::hasTable('{$new}'", $newContent);
                // constrained('old_name')
                $newContent = preg_replace("/constrained\(\s*['\"]{$old}['\"]\s*\)/i", "constrained('{$new}')", $newContent);
                // ->on('old_name')
                $newContent = preg_replace("/->on\(\s*['\"]{$old}['\"]\s*\)/i", "->on('{$new}')", $newContent);

                // Handle empty constrained() by inferring foreign key column
                $singular = (substr($old, -1) === 's' && substr($old, -2) !== 'is') ? substr($old, 0, -1) : $old;
                $fk = $singular . '_id';
                $newContent = preg_replace("/foreignId\(\s*['\"]{$fk}['\"]\s*\)([^;]*?)->constrained\(\s*\)/i", "foreignId('{$fk}')$1->constrained('{$new}')", $newContent);
            }

            if ($content !== $newContent) {
                File::put($file, $newContent);
                $this->line("Updated migration: " . $file->getFilename());
            }
        }

        // 2. Update Models
        $modelFiles = File::allFiles(app_path('Models'));
        foreach ($modelFiles as $file) {
            if ($file->getExtension() !== 'php') continue;
            
            $content = File::get($file);
            $newContent = $content;
            $changed = false;

            foreach ($this->tableMap as $old => $new) {
                // Look for: protected $table = 'old_name';
                if (preg_match('/protected\s+\$table\s*=\s*[\'"]' . $old . '[\'"];/i', $newContent)) {
                    $newContent = preg_replace('/protected\s+\$table\s*=\s*[\'"]' . $old . '[\'"];/i', "protected \$table = '{$new}';", $newContent);
                    $changed = true;
                }
            }
            
            // If model didn't explicitly have a table definition but relied on convention, we might need to add it,
            // but for simplicity, we assume models that are explicitly mapping have $table set, or we can just append it.
            // Let's add $table property to models that don't have it if their classname matches the old table convention.
            foreach ($this->tableMap as $old => $new) {
                $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $old)));
                if (preg_match('/class\s+' . $className . '\s+extends/i', $newContent) && !preg_match('/protected\s+\$table/i', $newContent)) {
                    $replacement = "class {$className} extends Model\n{\n    protected \$table = '{$new}';\n";
                    // Need to capture the exact extends string to be safe
                    $newContent = preg_replace('/class\s+' . $className . '\s+extends\s+([a-zA-Z0-9_\\\\]+)\s*\{/i', "class {$className} extends $1\n{\n    protected \$table = '{$new}';", $newContent);
                    $changed = true;
                }
            }

            if ($changed) {
                File::put($file, $newContent);
                $this->line("Updated model: " . $file->getRelativePathname());
            }
        }

        $this->info('Mass rename completed. Please verify changes and run tests.');
        return Command::SUCCESS;
    }
}
