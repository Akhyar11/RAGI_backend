#!/bin/bash
# ==============================================================================
# AUDIT 05: Audit Log Reviewer (BE) — AI Muse Spark Strict (Full Diff, tanpa regex)
# ==============================================================================

echo "📝 [Audit 6/9: Audit Log Observer] Memeriksa perubahan dengan AI (AI Muse Spark 1.3)..."

export PATH="$HOME/.local/bin:$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "app/Models/**" "app/Observers/**" "app/Services/**" "app/Http/Controllers/**" "app/Providers/**")
else
    STAGED_DIFF=$(git diff --cached -- "app/Models/**" "app/Observers/**" "app/Services/**" "app/Http/Controllers/**" "app/Providers/**")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit Audit Log] Tidak ada perubahan Model yang diuji. Skip."
    exit 0
fi

# DEEP AI AUDIT
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Audit Log Standard Laravel (Strict Backend Reviewer, AI Muse Spark 1.3).
Periksa FULL Git Diff berikut secara SANGAT KETAT terhadap seluruh standar audit log & observer. Penilaian MURNI oleh AI dari diff — tidak ada pre-check regex.

Aturan Baku (STRICT — setiap aturan bernomor, nilai hanya dari baris baru):
1. WAJIB catat aksi sensitif; DILARANG mengabaikan; read/view TIDAK wajib.
   - WAJIB dicatat: login berhasil, login gagal, create/update/delete sensitif, export (PDF/Excel), approve/reject.
   - SALAH: tambah endpoint `POST /users` (create user), `PUT /nilai`, `DELETE /users/{id}`, `POST /krs/approve`, `GET /laporan/export` tanpa `AuditLogService::record` / Observer.
   - BENAR: setiap aksi di atas memanggil audit log; `GET /mahasiswa` (read/view biasa) tanpa audit log adalah BENAR (tidak wajib).
2. WAJIB panggil `AuditLogService::record(module,action,tableName,recordId,old/new,request→ip/user_agent)`; DILARANG signature liar.
   - SALAH: `AuditLog::create([...])` manual tanpa service, `record('users','simpan',...)` (action tak standar), tanpa `recordId`, tanpa `request` sehingga `ip_address/user_agent` hilang.
   - BENAR: `AuditLogService::record(module:'IAM',action:'update',tableName:'users',recordId:$user->id,oldValues:$user->getOriginal(),newValues:$user->getChanges(),request:$request)` sehingga tersimpan `user_id,module,action,table_name,record_id,old_values,new_values,ip_address,user_agent`.
3. WAJIB enum module & action standar; DILARANG nilai karangan.
   - SALAH: `module:'USER'`, `module:'OBE'`, `action:'simpan'`, `action:'hapus'`, `action:'view'`.
   - BENAR module: `IAM|SPMB|SIAKAD|SIKEU|SIMPEG|LMS|UPM`.
   - BENAR action: `login|logout|create|update|delete|restore|approve|reject|export`.
4. WAJIB Observer untuk model sensitif baru + registrasi; DILARANG menunggu instruksi.
   - SALAH: model sensitif baru (keuangan/nilai/pendaftaran/user/role) tanpa `php artisan make:observer XObserver --model=X`, tanpa `wasChanged()/updated()/deleted()`, tanpa `User::observe(UserObserver::class)` di `AppServiceProvider`.
   - BENAR: `class UserObserver { public function updated(User $u){ if($u->wasChanged()) AuditLogService::record(...action:'update'...); } public function deleted(User $u){ AuditLogService::record(...action:'delete'...); } }` + registrasi `User::observe(UserObserver::class);` di `AppServiceProvider` — dibuat otomatis tanpa menunggu instruksi user.
5. WAJIB filter sensitif + getOriginal SEBELUM save + try-catch; DILARANG log bocor/menggagalkan request.
   - SALAH: `newValues` berisi `password/token`, `getOriginal()` dipanggil SETELAH `save()`, pencatatan tanpa `try-catch` hingga kegagalan log menggagalkan request.
   - BENAR: filter `password/token` dari `old/new_values`, ambil `$model->getOriginal()` SEBELUM `save()`, bungkus `AuditLogService::record(...)` dalam `try-catch` agar log tak gagalkan request.

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
- Jika kode bersih dan memenuhi standar audit log, jawab TEPAT: PASSED
- Jika ditemukan pelanggaran pada baris baru (+), awali respon dengan REJECTED dan berikan rincian lengkap:
  * File & Potongan Baris Melanggar: (nama file dan baris/kode yang melanggar)
  * Aturan yang Dilanggar: (nama aturan / standar audit log yang dilanggar)
  * Alasan Penolakan: (penjelasan detail mengapa kode tersebut melanggar)
  * Solusi / Rekomendasi Perbaikan: (solusi konkrit atau contoh kode perbaikan)
EOF

AI_EXIT_CODE=1
if [ "$AI_ENGINE" != "agy" ] && [ -x "$OPENCODE_BIN" ]; then
    RESULT=$(timeout 60s "$OPENCODE_BIN" run --pure -m "$MODEL" "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
fi

if [ $AI_EXIT_CODE -ne 0 ] && command -v agy &> /dev/null; then
    RESULT=$(timeout 60s agy --model gemini-3.8-flash-low --print "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
fi

rm -f "$PROMPT_FILE"

if [ $AI_EXIT_CODE -ne 0 ]; then
    echo "❌ [Audit Audit Log] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit Audit Log] REJECTED oleh AI (Muse Spark)!"
    echo "================================ DETAIL TEMUAN AUDIT ================================"
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    echo "💡 Harap perbaiki seluruh pelanggaran di atas sebelum melakukan commit."
    exit 1
elif ! echo "$RESULT" | grep -qi "PASSED"; then
    echo "❌ [Audit Audit Log] REJECTED: AI tidak memberikan keputusan PASSED yang valid!"
    echo "================================ DETAIL OUTPUT ====================================="
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    exit 1
else
    echo "✅ [Audit Audit Log] PASSED (Divalidasi AI Muse Spark 1.3)."
    exit 0
fi
