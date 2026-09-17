#!/bin/bash
# ==============================================================================
# AUDIT 09: Table Naming Standard Reviewer (BE) — STRICT HYBRID
# ==============================================================================

echo "🗄️ [Audit 9/9: Table Naming Standard] Memeriksa perubahan dengan AI (Opencode Muse)..."

export PATH="$HOME/.local/bin:$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "database/migrations/**" "app/Models/**")
    STAGED_FILES=$(git diff "$DIFF_TARGET" --name-only --diff-filter=ACM -- "database/migrations/*.php" "app/Models/**/*.php" "app/Models/*.php")
else
    STAGED_DIFF=$(git diff --cached -- "database/migrations/**" "app/Models/**")
    STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM -- "database/migrations/*.php" "app/Models/**/*.php" "app/Models/*.php")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit Table Naming Standard] Tidak ada perubahan migration atau model yang diuji. Skip."
    exit 0
fi

# ------------------------------------------------------------------------------
# 1. DETERMINISTIC PRE-CHECK
# ------------------------------------------------------------------------------
PREFIX_RE='^(core|iam|spmb|siakad|sikeu|simpeg|sinapra|sippm|lms|oauth|sso)_'
FRAMEWORK_TABLES='^(sessions|cache|jobs|failed_jobs|job_batches|password_resets|password_reset_tokens)$'

is_valid_table() {
    local t="$1"
    echo "$t" | grep -qP "$PREFIX_RE" && return 0
    echo "$t" | grep -qP "$FRAMEWORK_TABLES" && return 0
    return 1
}

FAILED_REGEX=0

while IFS= read -r file; do
    [ -f "$file" ] || continue
    if [ -n "$DIFF_TARGET" ]; then
        ADDED=$(git diff "$DIFF_TARGET" -- "$file" | grep '^+' | grep -v '^+++' | sed 's^+^^')
    else
        ADDED=$(git diff --cached -- "$file" | grep '^+' | grep -v '^+++' | sed 's^+^^')
    fi
    [ -z "$ADDED" ] && continue

    NEW_TABLES=$(echo "$ADDED" | grep -oP "Schema::create\(\s*'\K[^']+" | head -n 10)
    for t in $NEW_TABLES; do
        if ! is_valid_table "$t"; then
            echo "❌ [Audit Table Naming] Tabel '$t' di $file tanpa prefix modul."
            echo "   💡 Gunakan prefix modul (core_, spmb_, siakad_, sikeu_, simpeg_, sinapra_, sippm_, iam_, ...)."
            FAILED_REGEX=1
        fi
    done

    MODEL_TABLES=$(echo "$ADDED" | grep -oP "protected \\\$table\s*=\s*'\K[^']+" | head -n 10)
    for t in $MODEL_TABLES; do
        if ! is_valid_table "$t"; then
            echo "❌ [Audit Table Naming] property \$table = '$t' di $file tanpa prefix modul."
            FAILED_REGEX=1
        fi
    done

    FK_TABLES=$(echo "$ADDED" | grep -oP "constrained\(\s*'\K[^']+" | head -n 10)
    for t in $FK_TABLES; do
        if ! is_valid_table "$t"; then
            echo "❌ [Audit Table Naming] constrained('$t') di $file mengarah ke tabel tanpa prefix modul."
            FAILED_REGEX=1
        fi
    done
done <<< "$STAGED_FILES"

if [ $FAILED_REGEX -ne 0 ]; then
    echo "❌ [Audit Table Naming Standard] DITOLAK pada tahap pemeriksaan statis!"
    exit 1
fi

# ------------------------------------------------------------------------------
# 2. DEEP AI AUDIT (Opencode Model Muse) — FULL DIFF
# ------------------------------------------------------------------------------
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Database Table Naming Standard (Strict Backend Reviewer).
Periksa Git Diff berikut HANYA terhadap aturan Table Naming Standard:

Aturan Baku (STRICT):
1. PREFIX MODUL WAJIB PADA SCHEMA::CREATE:
   - SEMUA nama tabel baru yang dibuat di migration (`Schema::create('nama_tabel', ...)`) WAJIB diawali dengan nama modulnya (contoh prefix: `core_`, `spmb_`, `siakad_`, `sikeu_`, `simpeg_`, `sinapra_`, `sippm_`, `lms_`, `oauth_`, `sso_`). DILARANG menggunakan nama tabel tunggal tanpa prefix modul (seperti `users`, `mahasiswa`, `tarif`).
2. FOREIGN KEY WAJIB KE TABEL BERPREFIX:
   - SEMUA relasi foreign key pada migration (`constrained('nama_tabel')`) WAJIB mengarah pada tabel yang memiliki prefix modul yang sah.
3. MODEL PROPERTY $TABLE:
   - Model baru atau dimodifikasi harus mendefinisikan property `protected $table` yang mereferensikan nama tabel berprefix modul.

Catatan:
- HANYA periksa baris-baris kode baru yang DITAMBAHKAN atau DIUBAH (diawali tanda `+`). JANGAN menolak baris konteks yang tidak diubah.

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
    RESULT=$(timeout 20s "$OPENCODE_BIN" run --pure -m "$MODEL" "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
fi

if [ $AI_EXIT_CODE -ne 0 ] && command -v agy &> /dev/null; then
    RESULT=$(timeout 30s agy --model gemini-3.8-flash-low --print "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
fi

rm -f "$PROMPT_FILE"

if [ $AI_EXIT_CODE -ne 0 ]; then
    echo "❌ [Audit Table Naming Standard] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit Table Naming Standard] REJECTED oleh AI (Muse)!"
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
    echo "✅ [Audit Table Naming Standard] PASSED (Divalidasi oleh AI Opencode Muse)."
    exit 0
fi
