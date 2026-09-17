#!/bin/bash
# ==============================================================================
# AUDIT 04: PSR-4 Namespace Reviewer (BE) — STRICT HYBRID
# ==============================================================================

echo "📦 [Audit 5/9: PSR-4 Namespace] Memeriksa perubahan dengan AI (Opencode Muse)..."

export PATH="$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "app/**")
    STAGED_FILES=$(git diff "$DIFF_TARGET" --name-only --diff-filter=A -- "app/**/*.php" "app/*.php")
else
    STAGED_DIFF=$(git diff --cached -- "app/**")
    STAGED_FILES=$(git diff --cached --name-only --diff-filter=A -- "app/**/*.php" "app/*.php")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit PSR-4 Namespace] Tidak ada file app/ yang diuji. Skip."
    exit 0
fi

# ------------------------------------------------------------------------------
# 1. DETERMINISTIC PRE-CHECK
# ------------------------------------------------------------------------------
FAILED_REGEX=0

while IFS= read -r file; do
    [ -f "$file" ] || continue
    EXPECTED=$(dirname "$file" | sed 's|^app|App|' | tr '/' '\\')
    DECLARED=$(grep -m1 -oP '^namespace\s+\K[^;]+' "$file" || true)
    if [ -z "$DECLARED" ]; then
        echo "❌ [Audit PSR-4] $file tidak mendeklarasikan namespace."
        FAILED_REGEX=1
    elif [ "$DECLARED" != "$EXPECTED" ]; then
        echo "❌ [Audit PSR-4] Namespace $file salah: '$DECLARED', seharusnya '$EXPECTED'."
        FAILED_REGEX=1
    fi
done <<< "$STAGED_FILES"

if [ $FAILED_REGEX -ne 0 ]; then
    echo "❌ [Audit PSR-4 Namespace] DITOLAK pada tahap pemeriksaan statis!"
    exit 1
fi

# ------------------------------------------------------------------------------
# 2. DEEP AI AUDIT (Opencode Model Muse) — FULL DIFF
# ------------------------------------------------------------------------------
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus PSR-4 Namespace & PHP Standards Laravel (Strict Backend Reviewer).
Periksa Git Diff berikut HANYA terhadap Aturan PSR-4 & Coding Standards:

Aturan Baku (STRICT):
1. KESESUAIAN NAMESPACE DENGAN PATH:
   - Setiap file PHP baru atau diubah di dalam `app/` WAJIB mendeklarasikan namespace yang presisi sesuai direktori fisiknya (contoh: `app/Http/Controllers/System/FooController.php` -> `namespace App\Http\Controllers\System;`).
2. KESESUAIAN NAMA CLASS & FILE:
   - Nama class/interface/trait WAJIB persis sama dengan nama file tanpa ekstensi `.php`.
3. STANDAR PSR-12:
   - Format PHP tag pembuka (`<?php`), deklarasi namespace di baris atas, penulisan use statement yang rapi.

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
- Jika kode bersih dan memenuhi standar PSR-4, jawab TEPAT: PASSED
- Jika ditemukan pelanggaran pada baris baru (+), awali respon dengan REJECTED dan berikan rincian lengkap:
  * File & Potongan Baris Melanggar: (nama file dan baris/kode yang melanggar)
  * Aturan yang Dilanggar: (nama aturan / standar PSR-4 yang dilanggar)
  * Alasan Penolakan: (penjelasan detail mengapa kode tersebut melanggar)
  * Solusi / Rekomendasi Perbaikan: (solusi konkrit atau contoh kode perbaikan)
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
    echo "❌ [Audit PSR-4 Namespace] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit PSR-4 Namespace] REJECTED oleh AI (Muse)!"
    echo "================================ DETAIL TEMUAN AUDIT ================================"
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    echo "💡 Harap perbaiki seluruh pelanggaran di atas sebelum melakukan commit."
    exit 1
elif ! echo "$RESULT" | grep -qi "PASSED"; then
    echo "❌ [Audit PSR-4 Namespace] REJECTED: AI tidak memberikan keputusan PASSED yang valid!"
    echo "================================ DETAIL OUTPUT ====================================="
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    exit 1
else
    echo "✅ [Audit PSR-4 Namespace] PASSED (Divalidasi oleh AI Opencode Muse)."
    exit 0
fi
