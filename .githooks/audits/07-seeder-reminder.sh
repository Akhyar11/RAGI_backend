#!/bin/bash
# ==============================================================================
# AUDIT 07: Seeder & Migration Reviewer (BE) — AI Muse Spark Strict (Full Diff, tanpa regex)
# ==============================================================================

echo "🌱 [Audit 8/9: Seeder Reminder] Memeriksa perubahan dengan AI (AI Muse Spark 1.3)..."

export PATH="$HOME/.local/bin:$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "database/migrations/**/*.php" "database/seeders/**/*.php" "database/factories/**/*.php")
else
    STAGED_DIFF=$(git diff --cached -- "database/migrations/**/*.php" "database/seeders/**/*.php" "database/factories/**/*.php")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit Seeder] Tidak ada migration/seeder yang diuji. Skip."
    exit 0
fi

# DEEP AI AUDIT
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Database Migration & Seeder Laravel (Strict Backend Reviewer, AI Muse Spark 1.3).
Periksa FULL Git Diff migration/seeder/factory berikut secara SANGAT KETAT. Penilaian MURNI oleh AI dari diff — tidak ada pre-check regex.

Aturan Baku (STRICT — setiap aturan bernomor, nilai hanya dari baris baru):
1. WAJIB `updateOrCreate/firstOrCreate`; DILARANG `::create()` di seeder.
   - SALAH: `+Role::create(['name'=>'Super Admin','slug'=>'super-admin']);`.
   - BENAR: `+Role::updateOrCreate(['slug'=>'super-admin'], ['name'=>'Super Admin','is_active'=>true]);`.
2. WAJIB DatabaseSeeder koordinator terurut, IAM pertama; DILARANG urutan acak/tanpa koordinator.
   - SALAH: seeder modul dipanggil sebelum Role/Permission/AdminUser, atau tabel master baru tanpa didaftarkan di `DatabaseSeeder::run()`.
   - BENAR: `+$this->call([\\Database\\Seeders\\IAM\\RoleSeeder::class, \\Database\\Seeders\\IAM\\PermissionSeeder::class, \\Database\\Seeders\\IAM\\AdminUserSeeder::class, ...]);` (IAM pertama).
3. WAJIB RoleSeeder 8 slug + AdminUserSeeder via env; DILARANG hardcode password.
   - SALAH: role kurang/beda slug, `+'password'=>Hash::make('admin123')`, `+'user_type'=>'admin'`.
   - BENAR 8 slug: `super-admin, admin-iam, dosen, dosen-wali, mahasiswa, admin-spmb, admin-siakad, admin-sikeu`; `+User::updateOrCreate(['email'=>env('SUPER_ADMIN_EMAIL','superadmin@kampus.ac.id')], ['username'=>'superadmin','password'=>Hash::make(env('SUPER_ADMIN_PASSWORD','password')),'is_active'=>true,'is_verified'=>true])` + attach role via `roles()->attach`.
4. DILARANG `factory()` untuk master; WAJIB idempoten 10x; WAJIB env untuk lingkungan.
   - SALAH: `+Role::factory()->create()`, seeder error saat dijalankan kedua kali (duplicate), email/password admin hardcode.
   - BENAR: factory hanya untuk dummy testing; seeder dijalankan 10x hasil sama; email/password/lingkungan via `env()`.
5. WAJIB konvensi kolom/tipe/indeks/FK; DILARANG tipe longgar.
   - SALAH: FK `userId`, boolean `active`, waktu `login_time`, uang `float('biaya')`, NIK integer, tanpa `timestamps()/softDeletes()`, FK tanpa `onDelete`, uang tanpa `decimal(15,2)`.
   - BENAR: FK `*_id` (`user_id,role_id`), boolean `is_/has_` (`is_active,has_paid`), waktu `_at/_date`, path `_path`, `$table->timestamps(); $table->softDeletes();`, indeks `->unique()/->index()` + composite (`->index(['modul','action','created_at'])`, `->unique(['krs_id','kelas_id'])`), FK `->constrained()->onDelete('cascade|set null|restrict')`, tipe `decimal(15,2)` uang / `decimal(5,2)` nilai / `decimal(4,2)` IPK / `text` / `json` / `string(20)` HP-NIK-NIM.
6. WAJIB `down()` `dropIfExists` kebalikan urutan `up()`; DILARANG down kosong/tak simetris.
   - SALAH: `+public function down(){ /* kosong */ }` atau drop induk sebelum anak ber-FK.
   - BENAR: `+Schema::dropIfExists('role_permissions'); +Schema::dropIfExists('user_roles'); +Schema::dropIfExists('permissions'); +Schema::dropIfExists('roles');` (anak dulu, induk kemudian).

Catatan:
- HANYA periksa baris baru (+) — baris konteks tanpa `+` WAJIB diabaikan.

Git Diff:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$STAGED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
PENTING: Jawab HANYA secara langsung tanpa memanggil tool atau membaca file.
Format Respon:
- Jika migration/seeder sudah baik dan simetris, jawab TEPAT: PASSED
- Jika ditemukan pelanggaran pada baris baru (+), awali respon dengan REJECTED dan berikan rincian lengkap:
  * File & Potongan Baris Melanggar: (nama file dan baris/kode yang bersangkutan)
  * Aturan yang Dilanggar: (nama aturan migration/seeder yang dilanggar)
  * Alasan Penolakan: (penjelasan detail mengapa ditolak)
  * Solusi / Rekomendasi Perbaikan: (solusi konkrit atau contoh kode perbaikan)
EOF

AI_EXIT_CODE=1
if [ "$AI_ENGINE" != "agy" ] && [ -x "$OPENCODE_BIN" ]; then
    RESULT=$(timeout 20s "$OPENCODE_BIN" run --pure -m "$MODEL" "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
fi

if [ $AI_EXIT_CODE -ne 0 ] && command -v agy &> /dev/null; then
    RESULT=$(timeout 30s agy --model gemini-3.8-flash-low --print "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
fi

rm -f "$PROMPT_FILE"

if [ $AI_EXIT_CODE -ne 0 ]; then
    echo "❌ [Audit Seeder] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit Seeder] REJECTED oleh AI (Muse Spark)!"
    echo "================================ DETAIL TEMUAN AUDIT ================================"
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    echo "💡 Harap perbaiki seluruh pelanggaran di atas sebelum melakukan commit."
    exit 1
elif ! echo "$RESULT" | grep -qi "PASSED"; then
    echo "❌ [Audit Seeder] REJECTED: AI tidak memberikan keputusan PASSED yang valid!"
    echo "================================ DETAIL OUTPUT ====================================="
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    exit 1
else
    echo "✅ [Audit Seeder] PASSED (Divalidasi AI Muse Spark 1.3)."
    exit 0
fi
