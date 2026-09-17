#!/bin/bash

echo "📦 [Audit 5/9: PSR-4 Namespace] Memeriksa perubahan dengan AI (Opencode Muse)..."

export PATH="$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "app/**")
else
    STAGED_DIFF=$(git diff --cached -- "app/**")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit PSR-4 Namespace] Tidak ada file app/ yang diuji. Skip."
    exit 0
fi

TRUNCATED_DIFF=$(echo "$STAGED_DIFF" | head -n 400)
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus PSR-4 Namespace & PHP Standards Laravel.
Periksa Git Diff berikut HANYA terhadap Aturan PSR-4 & Coding Standards:

Aturan:
1. KESESUAIAN NAMESPACE DENGAN PATH:
   - Setiap file PHP baru atau diubah di dalam `app/` WAJIB mendeklarasikan namespace yang presisi sesuai direktori fisiknya (contoh: `app/Http/Controllers/System/FooController.php` -> `namespace App\Http\Controllers\System;`).
2. KESESUAIAN NAMA CLASS & FILE:
   - Nama class/interface/trait WAJIB persis sama dengan nama file tanpa ekstensi `.php`.
3. STANDAR PSR-12:
   - Format PHP tag pembuka (`<?php`), deklarasi namespace di baris atas, penulisan use statement yang rapi.

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
- PASSED jika kode bersih dan memenuhi standar PSR-4.
- REJECTED: [detail alasan pelanggaran] jika ditemukan ketidaksesuaian namespace atau nama class pada baris baru (+).
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
    echo "⚠️ [Audit PSR-4 Namespace] AI reviewer tidak merespons (Exit: $AI_EXIT_CODE), melanjutkan..."
    exit 0
fi

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit PSR-4 Namespace] REJECTED oleh AI (Muse)!"
    echo "$RESULT" | grep -i "REJECTED"
    exit 1
else
    echo "✅ [Audit PSR-4 Namespace] PASSED (Divalidasi oleh AI Opencode Muse)."
    exit 0
fi
