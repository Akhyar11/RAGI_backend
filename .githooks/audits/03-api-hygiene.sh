#!/bin/bash
# ==============================================================================
# AUDIT 03: API Hygiene Reviewer (BE) — STRICT HYBRID
# ==============================================================================

echo "🧹 [Audit 4/9: API Hygiene] Memeriksa perubahan dengan AI (Opencode Muse)..."

export PATH="$HOME/.local/bin:$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "app/**")
    STAGED_FILES=$(git diff "$DIFF_TARGET" --name-only --diff-filter=ACM -- "app/**/*.php" "app/*.php")
else
    STAGED_DIFF=$(git diff --cached -- "app/**")
    STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM -- "app/**/*.php" "app/*.php")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit API Hygiene] Tidak ada file app/ yang diuji. Skip."
    exit 0
fi

# ------------------------------------------------------------------------------
# 1. DETERMINISTIC PRE-CHECK
# ------------------------------------------------------------------------------
FAILED_REGEX=0

while IFS= read -r file; do
    [ -f "$file" ] || continue
    if [ -n "$DIFF_TARGET" ]; then
        ADDED=$(git diff "$DIFF_TARGET" -- "$file" | grep '^+' | grep -v '^+++' | sed 's^+^^')
    else
        ADDED=$(git diff --cached -- "$file" | grep '^+' | grep -v '^+++' | sed 's^+^^')
    fi
    [ -z "$ADDED" ] && continue

    DEBUG_HIT=$(echo "$ADDED" | grep -nP '(?<!\w)(dd|dump|var_dump|print_r|ray|die|exit)\s*\(' | head -n 3)
    if [ -n "$DEBUG_HIT" ]; then
        echo "❌ [Audit API Hygiene] Sisa debug di $file:"
        echo "$DEBUG_HIT" | sed 's/^/    /'
        echo "   💡 Hapus dd()/dump()/die()/exit() sebelum commit."
        FAILED_REGEX=1
    fi

    ENV_HIT=$(echo "$ADDED" | grep -nP '(?<!\w)env\s*\(' | head -n 3)
    if [ -n "$ENV_HIT" ]; then
        echo "❌ [Audit API Hygiene] Pemanggilan env() di $file:"
        echo "$ENV_HIT" | sed 's/^/    /'
        echo "   💡 Konfigurasi WAJIB via config/ atau SystemSetting (DB), bukan env() langsung di app/."
        FAILED_REGEX=1
    fi
done <<< "$STAGED_FILES"

if [ $FAILED_REGEX -ne 0 ]; then
    echo "❌ [Audit API Hygiene] DITOLAK pada tahap pemeriksaan statis!"
    exit 1
fi

# ------------------------------------------------------------------------------
# 2. DEEP AI AUDIT (Opencode Model Muse) — FULL DIFF
# ------------------------------------------------------------------------------
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus API Hygiene Backend Laravel (Strict Backend Reviewer).
Periksa Git Diff berikut HANYA terhadap Aturan API Hygiene:

Aturan Baku (STRICT):
1. DILARANG SISA FUNGSI DEBUG:
   - DILARANG KERAS meninggalkan fungsi debug seperti `dd(...)`, `dump(...)`, `var_dump(...)`, `print_r(...)`, `ray(...)`, `die(...)`, atau `exit(...)` pada baris kode baru.
2. DILARANG PEMANGGILAN ENV() LANGSUNG:
   - DILARANG memanggil helper `env(...)` secara langsung di dalam direktori `app/` (Controller, Model, Service). Seluruh pembacaan environment variable WAJIB melalui file konfigurasi `config('...')` atau database `SystemSetting`.

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
- Jika kode bersih dan memenuhi standar API Hygiene, jawab TEPAT: PASSED
- Jika ditemukan pelanggaran pada baris baru (+), awali respon dengan REJECTED dan berikan rincian lengkap:
  * File & Potongan Baris Melanggar: (nama file dan baris/kode yang melanggar)
  * Aturan yang Dilanggar: (nama aturan / standar API Hygiene yang dilanggar)
  * Alasan Penolakan: (penjelasan detail mengapa kode tersebut melanggar)
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
    echo "❌ [Audit API Hygiene] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit API Hygiene] REJECTED oleh AI (Muse)!"
    echo "================================ DETAIL TEMUAN AUDIT ================================"
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    echo "💡 Harap perbaiki seluruh pelanggaran di atas sebelum melakukan commit."
    exit 1
elif ! echo "$RESULT" | grep -qi "PASSED"; then
    echo "❌ [Audit API Hygiene] REJECTED: AI tidak memberikan keputusan PASSED yang valid!"
    echo "================================ DETAIL OUTPUT ====================================="
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    exit 1
else
    echo "✅ [Audit API Hygiene] PASSED (Divalidasi oleh AI Opencode Muse)."
    exit 0
fi
