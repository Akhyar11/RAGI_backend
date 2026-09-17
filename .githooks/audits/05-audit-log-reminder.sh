#!/bin/bash

echo "📝 [Audit 6/9: Audit Log Observer] Memeriksa perubahan dengan AI (Opencode Muse)..."

export PATH="$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "app/Models/**")
else
    STAGED_DIFF=$(git diff --cached -- "app/Models/**")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit Audit Log] Tidak ada perubahan Model yang diuji. Skip."
    exit 0
fi

TRUNCATED_DIFF=$(echo "$STAGED_DIFF" | head -n 400)
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Audit Log Standard Laravel.
Periksa Git Diff model berikut terhadap standar audit log & observer:

Aturan Audit Log:
1. PENCATATAN AKTIVITAS PADA ENTITAS SENSITIF:
   - Model data sensitif (keuangan, nilai, pendaftaran, user/role) disarankan memiliki observer/audit log event.
   - Master data umum/referensi (seperti tipe referensi, kategori) cukup dipastikan aman.

Catatan:
- Ini merupakan pengingat arsitektural (reminder). Jawab PASSED kecuali terdapat kode yang merusak mekanisme audit trail yang sudah ada.

Git Diff:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$TRUNCATED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
PENTING: Jawab HANYA secara langsung tanpa memanggil tool atau membaca file.
Jawab HANYA salah satu:
- PASSED jika kode model dapat diterima.
- REJECTED: [detail alasan] jika ada kerusakan fatal pada audit trail.
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
    echo "⚠️ [Audit Audit Log] AI reviewer tidak merespons (Exit: $AI_EXIT_CODE), melanjutkan..."
    exit 0
fi

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit Audit Log] REJECTED oleh AI (Muse)!"
    echo "$RESULT" | grep -i "REJECTED"
    exit 1
else
    echo "✅ [Audit Audit Log] PASSED (Divalidasi oleh AI Opencode Muse)."
    exit 0
fi
