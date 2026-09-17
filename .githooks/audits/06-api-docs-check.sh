#!/bin/bash
# ==============================================================================
# AUDIT 06: API Documentation Reviewer (BE) — STRICT HYBRID
# ==============================================================================

echo "📚 [Audit 7/9: API Documentation] Memeriksa perubahan dengan AI (Opencode Muse)..."

export PATH="$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    NEW_CONTROLLERS=$(git diff "$DIFF_TARGET" --name-only --diff-filter=A -- "app/Http/Controllers/**/*.php" "app/Http/Controllers/*.php")
    STAGED_DOCS=$(git diff "$DIFF_TARGET" --name-only -- "docs/**/*.md")
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "app/Http/Controllers/**" "docs/**")
else
    NEW_CONTROLLERS=$(git diff --cached --name-only --diff-filter=A -- "app/Http/Controllers/**/*.php" "app/Http/Controllers/*.php")
    STAGED_DOCS=$(git diff --cached --name-only -- "docs/**/*.md")
    STAGED_DIFF=$(git diff --cached -- "app/Http/Controllers/**" "docs/**")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit API Documentation] Tidak ada perubahan controller/docs yang diuji. Skip."
    exit 0
fi

# Cek apakah ada controller baru tanpa berkas docs
if [ -n "$NEW_CONTROLLERS" ] && [ -z "$STAGED_DOCS" ]; then
    echo "❌ [Audit API Documentation] Controller baru tanpa berkas dokumentasi di docs/api/:"
    echo "$NEW_CONTROLLERS" | sed 's/^/    /'
    echo "   💡 Setiap controller baru WAJIB memiliki docs/api/{Modul}/{Controller}.md."
    exit 1
fi

PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus API Documentation Standard Laravel (Strict Backend Reviewer).
Periksa Git Diff berikut terhadap kelengkapan dan format dokumentasi API:

Aturan Baku (STRICT):
1. KELENGKAPAN ENDPOINT DOKUMENTASI:
   - Setiap endpoint controller baru wajib terdokumentasi (method, route path, parameter request, contoh response).
   - Format dokumentasi menggunakan markdown rapi di folder `docs/api/`.

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
- Jika kode bersih dan dokumentasi memadai, jawab TEPAT: PASSED
- Jika ditemukan pelanggaran pada baris baru (+), awali respon dengan REJECTED dan berikan rincian lengkap:
  * File & Potongan Baris Melanggar: (nama file controller/docs dan baris/kode yang bersangkutan)
  * Aturan yang Dilanggar: (nama aturan dokumentasi API yang dilanggar)
  * Alasan Penolakan: (penjelasan detail mengapa ditolak)
  * Solusi / Rekomendasi Perbaikan: (solusi konkrit atau template dokumen yang wajib dibuat)
EOF

if [ -x "$OPENCODE_BIN" ]; then
    RESULT=$(timeout 45s "$OPENCODE_BIN" run --pure -m "$MODEL" "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
elif command -v agy &> /dev/null; then
    RESULT=$(timeout 30s agy --print "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
else
    AI_EXIT_CODE=127
fi

rm -f "$PROMPT_FILE"

if [ $AI_EXIT_CODE -ne 0 ]; then
    echo "❌ [Audit API Documentation] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit API Documentation] REJECTED oleh AI (Muse)!"
    echo "================================ DETAIL TEMUAN AUDIT ================================"
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    echo "💡 Harap perbaiki seluruh pelanggaran di atas sebelum melakukan commit."
    exit 1
elif ! echo "$RESULT" | grep -qi "PASSED"; then
    echo "❌ [Audit API Documentation] REJECTED: AI tidak memberikan keputusan PASSED yang valid!"
    echo "================================ DETAIL OUTPUT ====================================="
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    exit 1
else
    echo "✅ [Audit API Documentation] PASSED (Divalidasi oleh AI Opencode Muse)."
    exit 0
fi
