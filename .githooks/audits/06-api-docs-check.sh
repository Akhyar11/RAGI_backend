#!/bin/bash

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

TRUNCATED_DIFF=$(echo "$STAGED_DIFF" | head -n 400)
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus API Documentation Standard Laravel.
Periksa Git Diff berikut terhadap kelengkapan dan format dokumentasi API:

Aturan API Documentation:
1. KELENGKAPAN ENDPOINT DOKUMENTASI:
   - Setiap endpoint controller baru wajib terdokumentasi (method, route path, parameter request, contoh response).
   - Format dokumentasi menggunakan markdown rapi di folder `docs/api/`.

Git Diff:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$TRUNCATED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
PENTING: Jawab HANYA secara langsung tanpa memanggil tool atau membaca file.
Jawab HANYA salah satu:
- PASSED jika dokumentasi API memadai atau tidak ada controller baru yang melanggar.
- REJECTED: [detail alasan] jika dokumentasi tidak memadai.
EOF

if [ -x "$OPENCODE_BIN" ]; then
    RESULT=$(timeout 30s "$OPENCODE_BIN" run --pure -m "$MODEL" "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
elif command -v agy &> /dev/null; then
    RESULT=$(timeout 20s agy --print "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
else
    AI_EXIT_CODE=127
fi

rm -f "$PROMPT_FILE"

if [ $AI_EXIT_CODE -ne 0 ]; then
    echo "⚠️ [Audit API Documentation] AI reviewer tidak merespons (Exit: $AI_EXIT_CODE), melanjutkan..."
    exit 0
fi

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit API Documentation] REJECTED oleh AI (Muse)!"
    echo "$RESULT" | grep -i "REJECTED"
    exit 1
else
    echo "✅ [Audit API Documentation] PASSED (Divalidasi oleh AI Opencode Muse)."
    exit 0
fi
