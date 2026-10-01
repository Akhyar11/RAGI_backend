#!/bin/bash
# ==============================================================================
# AUDIT 03 (BE): API Documentation & Database Seeder Integrity (Unified AI Reviewer)
# ==============================================================================
# Memeriksa:
# 1. Kelengkapan Dokumentasi API di docs/api/{Modul}/{Controller}.md & indeks docs/README.md
# 2. Database Seeder untuk tabel master/referensi baru (firstOrCreate/updateOrCreate)
# ==============================================================================

echo "📚 [Audit 3/3: Docs & Seeders Integrity] Memeriksa dokumentasi API dan kelengkapan seeder dengan AI..."

export PATH="$HOME/.local/bin:$HOME/.opencode/bin:/usr/local/bin:$PATH"
AI_ENGINE="${AI_ENGINE:-agy}"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -M -- "app/Http/Controllers/**" "docs/**" "database/migrations/**" "database/seeders/**")
else
    STAGED_DIFF=$(git diff --cached -M -- "app/Http/Controllers/**" "docs/**" "database/migrations/**" "database/seeders/**")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit 3/3] Tidak ada perubahan controller, docs, migrasi, atau seeder yang diuji. Skip."
    exit 0
fi

if [ ${#STAGED_DIFF} -gt 100000 ]; then
    STAGED_DIFF="${STAGED_DIFF:0:100000}"$'\n\n[CATATAN: diff dipotong pada 100.000 karakter. Nilai HANYA yang terlihat di atas; JANGAN mengarang pelanggaran pada file yang tidak tampak.]'
fi

PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Dokumentasi API dan Database Seeder Laravel (AI Muse Spark 1.3).
Tugasmu adalah memeriksa FULL Git Diff berikut secara SANGAT KETAT terhadap kelengkapan dokumentasi API dan database seeder.

================================ STANDAR EVALUASI ================================

1. STANDAR DOKUMENTASI API:
   - Setiap controller baru atau endpoint yang ditambah/diubah WAJIB memiliki/memperbarui file dokumentasi di `docs/api/{Modul}/{Controller}.md` dan terdaftar pada tabel indeks `docs/README.md`.
   - Format docs WAJIB lengkap:
     * Header info: Modul, Base URL, Autentikasi, Status Dibuat/Diperbarui.
     * Tabel daftar endpoint: Method | Endpoint | Fungsi | Auth.
     * Request headers & Query parameters lengkap (default pagination: created_at, desc, per_page: 15, page: 1).
     * Request body (jika ada) dan contoh JSON response nyata (200/201, pagination, serta error 400/401/403/404/422).

2. STANDAR DATABASE SEEDER & MIGRATION:
   - Data awal / master data pada seeder WAJIB menggunakan `updateOrCreate` atau `firstOrCreate` (DILARANG `::create()` mentah yang gagal jika di-seed ulang).
   - Seeder idempoten (bisa dijalankan berkali-kali tanpa duplicate entry error).
   - Seeder master data baru WAJIB didaftarkan ke `DatabaseSeeder.php` atau koordinator modul terkait.

==================================================================================

Catatan Penilaian:
- HANYA periksa baris baru (+) — baris tanpa `+` WAJIB diabaikan.
- JANGAN menuduh simbol/komponen tidak terdefinisi jika baris import berada di luar potongan diff.

Git Diff:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$STAGED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
PENTING: Jawab HANYA secara langsung tanpa memanggil tool atau membaca file.
Format Respon:
- Jika kode bersih dan memenuhi standar dokumentasi dan seeder, jawab TEPAT: PASSED
- Jika ditemukan pelanggaran pada baris baru (+), awali respon dengan REJECTED dan berikan rincian:
  * File & Potongan Baris Melanggar: (nama file dan baris/kode yang melanggar)
  * Standar yang Dilanggar: (nama standar yang dilanggar)
  * Alasan Penolakan: (penjelasan detail)
  * Solusi / Rekomendasi Perbaikan: (contoh perbaikan konkrit)
EOF

AI_EXIT_CODE=1
if [ "$AI_ENGINE" != "agy" ] && [ -x "$OPENCODE_BIN" ]; then
    RESULT=$(timeout 90s "$OPENCODE_BIN" run --pure -m "$MODEL" "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
fi

if [ $AI_EXIT_CODE -ne 0 ] && command -v agy &> /dev/null; then
    RESULT=$(timeout 90s agy --model gemini-3.8-flash-low --print "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
fi

rm -f "$PROMPT_FILE"

if [ $AI_EXIT_CODE -ne 0 ]; then
    echo "❌ [Audit 3/3: Docs & Seeders Integrity] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit 3/3: Docs & Seeders Integrity] REJECTED oleh AI!"
    echo "================================ DETAIL TEMUAN AUDIT ================================"
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    echo "💡 Harap lengkapi dokumentasi API atau seeder di atas sebelum melakukan commit."
    exit 1
elif ! echo "$RESULT" | grep -qi "PASSED"; then
    echo "⚠️ [Audit 3/3: Docs & Seeders Integrity] Output AI tidak dikenali:"
    echo "$CLEAN_RESULT"
    exit 1
fi

echo "✅ [Audit 3/3: Docs & Seeders Integrity] PASSED (Divalidasi AI Muse Spark 1.3)."
exit 0
