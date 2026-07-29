<RULE[agent_skills]>
# Agent Directive: Selalu Periksa dan Gunakan Skill yang Relevan

Sebelum mengeksekusi tugas apapun, Anda WAJIB memeriksa daftar skill di bawah ini dan membaca SKILL.md yang relevan menggunakan `view_file` sebelum mulai coding.

## Daftar Skill yang Tersedia

| Skill | Path | Aktifkan Ketika |
|---|---|---|
| `unit-testing` | `.gemini/skills/unit_testing/SKILL.md` | Diminta membuat atau memperbaiki file test (`.test.php`, `Test.php`) |
| `api-crud-standard` | `.gemini/skills/api_crud_standard/SKILL.md` | Membuat atau memodifikasi endpoint API CRUD (Controller, Route, Request) |
| `service-layer-pattern` | `.gemini/skills/service_layer_pattern/SKILL.md` | Membuat Service class atau memindahkan logika dari Controller |
| `rbac-authorization` | `.gemini/skills/rbac_authorization/SKILL.md` | Mengimplementasikan pengecekan role/permission, Gate, Policy |
| `audit-log-standard` | `.gemini/skills/audit_log_standard/SKILL.md` | Menambahkan pencatatan jejak (audit log) pada aksi user |
| `api-error-handling` | `.gemini/skills/api_error_handling/SKILL.md` | Mengkonfigurasi Exception Handler atau menangani error di API |
| `file-upload-standard` | `.gemini/skills/file_upload_standard/SKILL.md` | Mengimplementasikan fitur upload file (dokumen, foto, dll.) |
| `database-migration-standard` | `.gemini/skills/database_migration_standard/SKILL.md` | Membuat atau memodifikasi file migrasi database |
| `seeder-standard` | `.gemini/skills/seeder_standard/SKILL.md` | Membuat atau memodifikasi file Seeder |
| `event-listener-standard` | `.gemini/skills/event_listener_standard/SKILL.md` | Membuat Event, Listener, atau alur reaktif antar-modul |
| `api-documentation` | `.gemini/skills/api_documentation/SKILL.md` | Membuat Controller baru ATAU memodifikasi endpoint yang sudah ada |

## Aturan Wajib

1. **BACA** SKILL.md yang relevan sebelum mulai coding. Jangan asumsikan — baca dulu.
2. **PATUHI SEMUA** ketentuan di dalam SKILL.md tanpa pengecualian.
3. Jika sebuah tugas melibatkan **lebih dari satu skill** (misalnya: membuat API CRUD + unit test-nya), baca **semua** SKILL.md yang relevan terlebih dahulu.
4. **JANGAN** menyimpang dari standar yang sudah ditetapkan di skill tanpa persetujuan eksplisit dari user.
</RULE[agent_skills]>
