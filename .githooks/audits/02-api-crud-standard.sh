#!/bin/bash
# ==============================================================================
# AUDIT 02: API CRUD Standard Reviewer (BE) — STRICT HYBRID
# ==============================================================================

echo "🤖 [Audit 3/9: API CRUD Standard] Memeriksa perubahan dengan AI (Opencode Muse)..."

export PATH="$HOME/.local/bin:$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "app/Http/Controllers/**" "app/Http/Requests/**")
    STAGED_FILES=$(git diff "$DIFF_TARGET" --name-only --diff-filter=ACM -- "app/Http/Controllers/**/*.php" "app/Http/Controllers/*.php")
else
    STAGED_DIFF=$(git diff --cached -- "app/Http/Controllers/**" "app/Http/Requests/**")
    STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM -- "app/Http/Controllers/**/*.php" "app/Http/Controllers/*.php")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit API CRUD Standard] Tidak ada perubahan controller/request yang diuji. Skip."
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

    INLINE_VALIDATE=$(echo "$ADDED" | grep -n '\$request->validate(' | head -n 3)
    if [ -n "$INLINE_VALIDATE" ]; then
        echo "❌ [Audit API CRUD] Validasi inline \$request->validate() di $file:"
        echo "$INLINE_VALIDATE" | sed 's/^/    /'
        echo "   💡 WAJIB memakai Form Request terpisah (Store*Request / Update*Request di app/Http/Requests/)."
        FAILED_REGEX=1
    fi
done <<< "$STAGED_FILES"

if [ $FAILED_REGEX -ne 0 ]; then
    echo "❌ [Audit API CRUD Standard] DITOLAK pada tahap pemeriksaan statis!"
    exit 1
fi

# ------------------------------------------------------------------------------
# 2. DEEP AI AUDIT (Opencode Model Muse) — FULL DIFF
# ------------------------------------------------------------------------------
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus API CRUD Standard Laravel (Strict Backend Reviewer).
Periksa Git Diff berikut HANYA terhadap Aturan Standar API CRUD:

Aturan Baku (STRICT):
1. FORM REQUEST WAJIB (NO INLINE VALIDATION):
   - Validasi data input WAJIB menggunakan Form Request terpisah di `app/Http/Requests/` (contoh: `StoreItemRequest`, `UpdateItemRequest`).
   - DILARANG memanggil validasi inline langsung seperti `$request->validate([...])` di dalam Controller.
2. SERVER-SIDE PAGINATION PADA INDEX:
   - Endpoint listing/index di Controller WAJIB menggunakan `paginate(...)` untuk mendukung limit & pagination. Dilarang keras fallback `->get()` atau `->all()` tanpa limitasi pada data tabel dinamis.
3. KONSISTENSI RESPONSE JSON:
   - Format response JSON harus memiliki envelope konsisten (`status`, `message`, `data`, `meta`).

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
- Jika kode bersih dan memenuhi API CRUD Standard, jawab TEPAT: PASSED
- Jika ditemukan pelanggaran pada baris baru (+), awali respon dengan REJECTED dan berikan rincian lengkap:
  * File & Potongan Baris Melanggar: (nama file dan baris/kode yang melanggar)
  * Aturan yang Dilanggar: (nama aturan / standar API CRUD yang dilanggar)
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
    echo "❌ [Audit API CRUD Standard] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit API CRUD Standard] REJECTED oleh AI (Muse)!"
    echo "================================ DETAIL TEMUAN AUDIT ================================"
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    echo "💡 Harap perbaiki seluruh pelanggaran di atas sebelum melakukan commit."
    exit 1
elif ! echo "$RESULT" | grep -qi "PASSED"; then
    echo "❌ [Audit API CRUD Standard] REJECTED: AI tidak memberikan keputusan PASSED yang valid!"
    echo "================================ DETAIL OUTPUT ====================================="
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    exit 1
else
    echo "✅ [Audit API CRUD Standard] PASSED (Divalidasi oleh AI Opencode Muse)."
    exit 0
fi
