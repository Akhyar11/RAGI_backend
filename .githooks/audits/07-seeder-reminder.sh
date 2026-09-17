#!/bin/bash

echo "🌱 [Audit 8/9: Seeder Reminder] Memeriksa perubahan dengan AI (Opencode Muse)..."

export PATH="$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "database/migrations/*.php" "database/seeders/*.php")
else
    STAGED_DIFF=$(git diff --cached -- "database/migrations/*.php" "database/seeders/*.php")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit Seeder] Tidak ada migration/seeder yang diuji. Skip."
    exit 0
fi

TRUNCATED_DIFF=$(echo "$STAGED_DIFF" | head -n 400)
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Database Migration & Seeder Laravel.
Periksa Git Diff migration/seeder berikut:

Aturan Seeder & Migration:
1. PEMBERIAN DATA AWAL (SEEDER):
   - Jika ada tabel master baru yang membutuhkan data inisialisasi awal sistem (seperti master referensi, tipe referensi, permission, default roles), pastikan seeder telah disiapkan atau disisipkan secara idempoten.
2. METODE DOWN() SIMETRIS:
   - Migration harus memiliki pembalikan `down()` yang membalikkan `up()` secara aman.

Catatan:
- Bersikap toleran jika migrasi hanya penambahan kolom kecil atau seeder sudah lengkap.

Git Diff:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$TRUNCATED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
PENTING: Jawab HANYA secara langsung tanpa memanggil tool atau membaca file.
Jawab HANYA salah satu:
- PASSED jika migration/seeder sudah baik.
- REJECTED: [detail alasan] jika ditemukan inkonsistensi parah pada migration/seeder.
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
    echo "⚠️ [Audit Seeder] AI reviewer tidak merespons (Exit: $AI_EXIT_CODE), melanjutkan..."
    exit 0
fi

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit Seeder] REJECTED oleh AI (Muse)!"
    echo "$RESULT" | grep -i "REJECTED"
    exit 1
else
    echo "✅ [Audit Seeder] PASSED (Divalidasi oleh AI Opencode Muse)."
    exit 0
fi
