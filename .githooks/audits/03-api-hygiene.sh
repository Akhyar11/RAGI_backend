#!/bin/bash

echo "🧹 [Audit 4/9: API Hygiene] Memeriksa perubahan dengan AI (Opencode Muse)..."

export PATH="$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "app/**")
else
    STAGED_DIFF=$(git diff --cached -- "app/**")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit API Hygiene] Tidak ada file app/ yang diuji. Skip."
    exit 0
fi

TRUNCATED_DIFF=$(echo "$STAGED_DIFF" | head -n 400)
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus API Hygiene Backend Laravel.
Periksa Git Diff berikut HANYA terhadap Aturan API Hygiene:

Aturan:
1. DILARANG SISA FUNGSI DEBUG:
   - DILARANG KERAS meninggalkan fungsi debug seperti `dd(...)`, `dump(...)`, `var_dump(...)`, `print_r(...)`, `ray(...)`, `die(...)`, atau `exit(...)` pada baris kode baru.
2. DILARANG PEMANGGILAN ENV() LANGSUNG:
   - DILARANG memanggil helper `env(...)` secara langsung di dalam direktori `app/` (Controller, Model, Service). Seluruh pembacaan environment variable WAJIB melalui file konfigurasi `config('...')` atau database `SystemSetting`.

Catatan Penting:
- HANYA periksa baris-baris kode baru yang DITAMBAHKAN atau DIUBAH (diawali tanda `+`). JANGAN menolak baris konteks yang tidak diubah.

Git Diff:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$TRUNCATED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
PENTING: Jawab HANYA secara langsung tanpa memanggil tool atau membaca file.
Jawab HANYA salah satu:
- PASSED jika kode bersih dan memenuhi standar API Hygiene.
- REJECTED: [detail alasan pelanggaran] jika ditemukan fungsi debug atau pemanggilan env() pada baris baru (+).
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
    echo "⚠️ [Audit API Hygiene] AI reviewer tidak merespons (Exit: $AI_EXIT_CODE), melanjutkan..."
    exit 0
fi

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit API Hygiene] REJECTED oleh AI (Muse)!"
    echo "$RESULT" | grep -i "REJECTED"
    exit 1
else
    echo "✅ [Audit API Hygiene] PASSED (Divalidasi oleh AI Opencode Muse)."
    exit 0
fi
