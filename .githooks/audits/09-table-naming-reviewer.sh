#!/bin/bash
# ==============================================================================
# AUDIT 09: Table Naming Standard Reviewer (BE) — AI Muse Spark Strict (Full Diff, tanpa regex)
# ==============================================================================

echo "🗄️ [Audit 9/9: Table Naming Standard] Memeriksa perubahan dengan AI (AI Muse Spark 1.3)..."

export PATH="$HOME/.local/bin:$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "database/migrations/**" "app/Models/**")
else
    STAGED_DIFF=$(git diff --cached -- "database/migrations/**" "app/Models/**")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit Table Naming Standard] Tidak ada perubahan migration atau model yang diuji. Skip."
    exit 0
fi

# DEEP AI AUDIT
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Database Table Naming Standard (Strict Backend Reviewer, AI Muse Spark 1.3).
Periksa FULL Git Diff berikut secara SANGAT KETAT terhadap aturan prefix modul. Penilaian MURNI oleh AI dari diff — tidak ada pre-check regex. AI WAJIB menangani single DAN double quote, `Schema::create`, `$table`, `constrained()` dengan/tanpa argumen, dan `->references()->on()`.

Aturan Baku (STRICT — setiap aturan bernomor, nilai hanya dari baris baru):
1. WAJIB prefix modul pada `Schema::create`; DILARANG tabel tanpa prefix.
   - SALAH: `+Schema::create('users', ...)`, `+Schema::create("mahasiswa", ...)`, `+Schema::create('tarif', ...)`.
   - BENAR: `+Schema::create('iam_users', ...)`, `+Schema::create("spmb_pendaftar", ...)`, `+Schema::create('siakad_mahasiswa', ...)`.
   - Prefix sah: `core_|iam_|spmb_|siakad_|sikeu_|simpeg_|sinapra_|sippm_|lms_|oauth_|sso_|upm_` (contoh BENAR: `core_settings`, `iam_roles`, `upm_standar`).
   - Dikecualikan (BENAR tanpa prefix): `sessions|cache|jobs|failed_jobs|job_batches|migrations|password_resets|password_reset_tokens|personal_access_tokens|audit_logs`.
   - Berlaku untuk single quote DAN double quote.
2. WAJIB FK ke tabel berprefix; DILARANG FK ke tabel tanpa prefix.
   - SALAH: `+->constrained('users')`, `+->constrained("mahasiswa")`, `+$table->foreign('x')->references('id')->on('users')`, `+$table->foreign('x')->references('id')->on("tarif")`.
   - BENAR: `+->constrained('iam_users')`, `+->constrained()` (tanpa argumen, mengikuti konvensi Laravel — BENAR), `+->references('id')->on('iam_users')`, versi double-quote yang setara.
   - AI WAJIB memeriksa `constrained()` dengan argumen, `constrained()` tanpa argumen (lolos), dan pola `->references(...)->on('...')` / `->references(...)->on("...")`.
3. WAJIB `protected $table` berprefix pada Model; DILARANG tanpa prefix.
   - SALAH: `+protected $table = 'users';`, `+protected $table = "mahasiswa";`.
   - BENAR: `+protected $table = 'iam_users';`, `+protected $table = "spmb_pendaftar";`.
   - Berlaku untuk single DAN double quote; baca dari baris `+$table` / `+protected $table`.

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
- Jika nama tabel/model sudah mematuhi standar prefix modul, jawab TEPAT: PASSED
- Jika ditemukan pelanggaran pada baris baru (+), awali respon dengan REJECTED dan berikan rincian lengkap:
  * File & Potongan Baris Melanggar: (nama file migration/model dan nama tabel yang melanggar)
  * Aturan yang Dilanggar: (nama aturan / standar penamaan tabel yang dilanggar)
  * Alasan Penolakan: (penjelasan detail mengapa tabel/model ditolak)
  * Solusi / Rekomendasi Perbaikan: (solusi konkrit atau contoh nama tabel berprefix yang benar)
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
    echo "❌ [Audit Table Naming Standard] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit Table Naming Standard] REJECTED oleh AI (Muse Spark)!"
    echo "================================ DETAIL TEMUAN AUDIT ================================"
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    echo "💡 Harap perbaiki seluruh pelanggaran di atas sebelum melakukan commit."
    exit 1
elif ! echo "$RESULT" | grep -qi "PASSED"; then
    echo "❌ [Audit Table Naming Standard] REJECTED: AI tidak memberikan keputusan PASSED yang valid!"
    echo "================================ DETAIL OUTPUT ====================================="
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    exit 1
else
    echo "✅ [Audit Table Naming Standard] PASSED (Divalidasi AI Muse Spark 1.3)."
    exit 0
fi
