<RULE[agent_skills]>
# Agent Directive: Selalu Periksa dan Gunakan Skill yang Relevan

Sebelum mengeksekusi tugas apapun, Anda WAJIB memeriksa daftar skill di bawah ini dan membaca SKILL.md yang relevan menggunakan `view_file` sebelum mulai coding.

## Daftar Skill yang Tersedia

| Skill                           | Path                                                    | Aktifkan Ketika                                                          |
| ------------------------------- | ------------------------------------------------------- | ------------------------------------------------------------------------ |
| `unit-testing`                | `.agent/skills/unit_testing/SKILL.md`                | Diminta membuat atau memperbaiki file test (`.test.php`, `Test.php`) |
| `api-crud-standard`           | `.agent/skills/api_crud_standard/SKILL.md`           | Membuat atau memodifikasi endpoint API CRUD (Controller, Route, Request) |
| `service-layer-pattern`       | `.agent/skills/service_layer_pattern/SKILL.md`       | Membuat Service class atau memindahkan logika dari Controller            |
| `rbac-authorization`          | `.agent/skills/rbac_authorization/SKILL.md`          | Mengimplementasikan pengecekan role/permission, Gate, Policy             |
| `audit-log-standard`          | `.agent/skills/audit_log_standard/SKILL.md`          | Menambahkan pencatatan jejak (audit log) pada aksi user                  |
| `api-error-handling`          | `.agent/skills/api_error_handling/SKILL.md`          | Mengkonfigurasi Exception Handler atau menangani error di API            |
| `file-upload-standard`        | `.agent/skills/file_upload_standard/SKILL.md`        | Mengimplementasikan fitur upload file (dokumen, foto, dll.)              |
| `database-migration-standard` | `.agent/skills/database_migration_standard/SKILL.md` | Membuat atau memodifikasi file migrasi database                          |
| `seeder-standard`             | `.agent/skills/seeder_standard/SKILL.md`             | Membuat atau memodifikasi file Seeder                                    |
| `event-listener-standard`     | `.agent/skills/event_listener_standard/SKILL.md`     | Membuat Event, Listener, atau alur reaktif antar-modul                   |
| `api-documentation`           | `.agent/skills/api_documentation/SKILL.md`           | Membuat Controller baru ATAU memodifikasi endpoint yang sudah ada        |
| `rbac-refactoring-standard`   | `.agent/skills/rbac_refactoring_standard/SKILL.md`   | Melakukan refaktor controller, model, migration, atau merancang pengecekan akses (RBAC) tanpa mengandalkan field statis. |
| `module-management-standard`| `.agent/skills/module_management_standard/SKILL.md`  | Merancang, menambah, atau memodifikasi modul aplikasi (Master Modul) di ekosistem kampus terintegrasi. |

## Aturan Wajib

1. **BACA** SKILL.md yang relevan sebelum mulai coding. Jangan asumsikan — baca dulu.
2. **PATUHI SEMUA** ketentuan di dalam SKILL.md tanpa pengecualian.
3. Jika sebuah tugas melibatkan **lebih dari satu skill** (misalnya: membuat API CRUD + unit test-nya), baca **semua** SKILL.md yang relevan terlebih dahulu.
4. **JANGAN** menyimpang dari standar yang sudah ditetapkan di skill tanpa persetujuan eksplisit dari user.
   </RULE[agent_skills]>

<RULE[github_push]>
# Git Push Policy
Agent **DILARANG KERAS** melakukan eksekusi perintah `git push` secara otomatis setelah menyelesaikan tugas atau setelah melakukan commit. Perintah `git push` HANYA boleh dieksekusi jika User memintanya secara eksplisit (misalnya: "push ke github").
</RULE[github_push]>

<RULE[no_hardcode_definition]>
# Zero Hardcode & Dynamic Entity Reference Policy

## Definisi Hardcode
Hardcode adalah suatu metode atau cara pengambilan data, pengiriman data, atau pengaturan data dengan **menyebutkan/mengetik nama atau label string secara langsung** (misalnya menyebutkan `'spmb'`, `'sikeu'`, atau string nama spesifik lainnya) alih-alih merujuk pada identitas entitas database.
Termasuk juga merespons API dengan **enum string statis** (seperti `'REGULER'`, `'KARYAWAN'`) jika data tersebut merujuk pada sebuah tabel master.

## Aturan Pengkodean
1. **Minimal Hardcode**: Sistem yang baik harus meminimalkan hardcode hingga 0%.
2. **Dilarang Keras Array/Enum Literal Statis**: DILARANG KERAS meng-hardcode opsi pilihan atau melakukan validasi backend menggunakan `in:VALUE1,VALUE2` jika nilai tersebut semestinya berasal dari tabel master database (contoh: `master_tipe_jalur`, `master_jalur_kelas`). Validasi WAJIB menggunakan rule `exists:nama_tabel,id`.
3. **Referensi ID Wajib**: Seluruh relasi, filter, dan query wajib menggunakan **referensi ID entitas** (seperti `module.id`, `tipe_jalur_id`, `jalur_kelas_id`, dsb.) yang diambil dari database, bukan berupa label string atau hardcode nama.
</RULE[no_hardcode_definition]>
