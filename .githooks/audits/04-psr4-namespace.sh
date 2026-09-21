#!/bin/bash
# ==============================================================================
# AUDIT 04: PSR-4 Namespace Reviewer (BE) — AI Muse Spark Strict (Full Diff, tanpa regex)
# ==============================================================================

echo "📦 [Audit 5/9: PSR-4 Namespace] Memeriksa perubahan dengan AI (AI Muse Spark 1.3)..."

export PATH="$HOME/.local/bin:$HOME/.opencode/bin:/usr/local/bin:$PATH"
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

# DEEP AI AUDIT
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus PSR-4 Namespace & PHP Standards Laravel (Strict Backend Reviewer, AI Muse Spark 1.3).
Periksa FULL Git Diff berikut secara SANGAT KETAT terhadap 3 aturan PSR-4. Penilaian MURNI oleh AI dari path file di header diff + baris baru — tidak ada pre-check regex.

Aturan Baku (STRICT — setiap aturan bernomor, nilai hanya dari baris baru):
1. WAJIB namespace persis = path; DILARANG namespace menyimpang.
   - SALAH: file `app/Http/Controllers/System/FooController.php` mendeklarasikan `+namespace App\\Http\\Controllers;` atau `+namespace App\\Services;`.
   - BENAR: `+namespace App\\Http\\Controllers\\System;` untuk path tersebut; `app/Services/IAM/UserService.php` → `+namespace App\\Services\\IAM;`.
   - Nilai dari header diff (`+++ b/app/...`) + baris `+namespace ...;`.
2. WAJIB nama class/interface/trait = nama file; DILARANG nama berbeda.
   - SALAH: file `UserService.php` berisi `+class AccountService`, file `UserObserver.php` berisi `+class UserListener`.
   - BENAR: `UserService.php` → `+class UserService`, `HasPermission.php` (trait) → `+trait HasPermission`, `UserPolicy.php` → `+class UserPolicy`.
3. WAJIB PSR-12; DILARANG format liar.
   - SALAH: tanpa `+<?php` pembuka, `namespace` tidak di baris atas, `use` berantakan/tidak terurut.
   - BENAR: `+<?php` baris pertama, lalu `+namespace App\\...;`, lalu blok `+use ...;` rapi, deklarasi class di bawahnya.

Catatan:
- HANYA periksa baris baru (+) — baris konteks tanpa `+` WAJIB diabaikan.

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

AI_EXIT_CODE=1
if [ "$AI_ENGINE" != "agy" ] && [ -x "$OPENCODE_BIN" ]; then
    RESULT=$(timeout 60s "$OPENCODE_BIN" run --pure -m "$MODEL" "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
fi

if [ $AI_EXIT_CODE -ne 0 ] && command -v agy &> /dev/null; then
    RESULT=$(timeout 60s agy --model gemini-3.8-flash-low --print "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
fi

rm -f "$PROMPT_FILE"

if [ $AI_EXIT_CODE -ne 0 ]; then
    echo "❌ [Audit PSR-4 Namespace] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit PSR-4 Namespace] REJECTED oleh AI (Muse Spark)!"
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
    echo "✅ [Audit PSR-4 Namespace] PASSED (Divalidasi AI Muse Spark 1.3)."
    exit 0
fi
