#!/bin/bash
# ==============================================================================
# AUDIT 06: API Documentation Reviewer (BE) — AI Muse Spark Strict (Full Diff, tanpa regex)
# ==============================================================================

echo "📚 [Audit 7/9: API Documentation] Memeriksa perubahan dengan AI (AI Muse Spark 1.3)..."

export PATH="$HOME/.local/bin:$HOME/.opencode/bin:/usr/local/bin:$PATH"
AI_ENGINE="${AI_ENGINE:-agy}"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -M -- "app/Http/Controllers/**" "docs/**")
else
    STAGED_DIFF=$(git diff --cached -M -- "app/Http/Controllers/**" "docs/**")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit API Documentation] Tidak ada perubahan controller/docs yang diuji. Skip."
    exit 0
fi

if [ ${#STAGED_DIFF} -gt 100000 ]; then
    STAGED_DIFF=$(echo "$STAGED_DIFF" | grep -E '^(\+\+\+|---|\+\s*|diff|@@)' | head -c 100000)
fi

# DEEP AI AUDIT
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus API Documentation Standard Laravel (Strict Backend Reviewer, AI Muse Spark 1.3).
Periksa FULL Git Diff berikut secara SANGAT KETAT terhadap kelengkapan dan format dokumentasi API. Penilaian MURNI oleh AI dari diff (file baru terlihat dari header diff `+++ b/...`) — tidak ada pre-check regex.

Aturan Baku (STRICT — setiap aturan bernomor, nilai hanya dari baris baru):
1. WAJIB `docs/api/{Modul}/{Controller}.md` per controller + `docs/README.md` indeks update; DILARANG controller/endpoint berubah tanpa docs.
   - SALAH: header diff `+++ b/app/Http/Controllers/IAM/UserController.php` (controller baru) tanpa `+++ b/docs/api/IAM/UserController.md` dan tanpa `+++ b/docs/README.md`; endpoint baru/ubah tanpa file docs baru/ubah.
   - BENAR: setiap controller baru/endpoint berubah disertai `docs/api/{Modul}/{Controller}.md` + update `docs/README.md` (tabel Controller|Deskripsi|Dokumen dengan link).
2. WAJIB template lengkap; DILARANG template bolong/placeholder.
   - SALAH: tanpa header `Modul/BaseURL/Auth/Dibuat/Diperbarui`, tanpa tabel `Method|Endpoint|Fungsi|Auth`, tanpa Headers (`Authorization Bearer`, `Accept`, `Content-Type`), tanpa Query Params (`search/sort_by/sort_order/per_page/page` + default `created_at/desc/15/1/maks 100`), tanpa Request Body, tanpa Response sukses+pagination+error nyata, tanpa catatan soft-delete/password.
   - BENAR: header `> **Modul**: IAM / **Base URL**: /api/users / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**`; tabel endpoint; Headers; Query Params lengkap; Request Body JSON nyata; Response `200/201` + pagination (`meta`) + error `401/403/404/422` nyata (contoh: `{"status":"error","message":"User tidak ditemukan."}`); catatan `soft-delete` dan `password tidak dikembalikan`.
   - DILARANG placeholder fiktif (`{"data":"..."}` tanpa struktur nyata) — WAJIB contoh nyata sesuai envelope API.
3. WAJIB tandai publik & emoji konsisten; DILARANG label acak.
   - SALAH: endpoint login tanpa `Auth: ❌ Publik`, memakai `[Ya]/[Tidak]` atau emoji acak.
   - BENAR: publik Auth `❌` (contoh `| POST | /api/auth/login | Login | ❌ Publik |`); emoji konsisten `✅`=Diperlukan/Tersedia, `❌`=Tidak diperlukan/Tidak tersedia, `⚠️`=Kondisional.

Catatan:
- HANYA periksa baris baru (+) — baris konteks tanpa `+` WAJIB diabaikan. File baru/controller baru/endpoint berubah tanpa docs lengkap = REJECTED.

- JANGAN menuduh sebuah simbol/komponen/ikon "tidak di-import" atau "tidak terdefinisi": diff hanya memuat potongan file, sehingga baris import sering berada DI LUAR diff. Validitas import sudah diverifikasi terpisah (tsc --noEmit untuk FE, php -l untuk BE). Laporkan hanya pelanggaran yang benar-benar terlihat pada baris (+).

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

AI_EXIT_CODE=1
if [ "$AI_ENGINE" != "agy" ] && [ -x "$OPENCODE_BIN" ]; then
    RESULT=$(timeout 120s "$OPENCODE_BIN" run --pure -m "$MODEL" "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
fi

if [ $AI_EXIT_CODE -ne 0 ] && command -v agy &> /dev/null; then
    RESULT=$(timeout 120s agy --model gemini-3.8-flash-low --print "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
fi

rm -f "$PROMPT_FILE"

if [ $AI_EXIT_CODE -ne 0 ]; then
    echo "❌ [Audit API Documentation] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit API Documentation] REJECTED oleh AI (Muse Spark)!"
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
    echo "✅ [Audit API Documentation] PASSED (Divalidasi AI Muse Spark 1.3)."
    exit 0
fi
