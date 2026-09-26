#!/bin/bash
# ==============================================================================
# AUDIT 10: File Storage Standard Reviewer (BE) — Strict
# Memastikan konsistensi penyimpanan & akses berkas (publik vs privat):
#  - Berkas privat disimpan di disk privat (store(..., private: true)).
#  - URL berkas privat WAJIB via FileStorageService (Signed URL), BUKAN URL
#    storage langsung (Storage::disk('public')->url(), r2.dev, R2_PRIVATE_URL).
# ==============================================================================

echo "🗄️ [Audit 10: File Storage Standard] Memeriksa penyimpanan & akses berkas..."

export PATH="$HOME/.local/bin:$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
AI_ENGINE="${AI_ENGINE:-agy}"
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "app/**" "routes/**")
else
    STAGED_DIFF=$(git diff --cached -- "app/**" "routes/**")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit File Storage Standard] Tidak ada perubahan app/routes yang diuji. Skip."
    exit 0
fi

if [ ${#STAGED_DIFF} -gt 80000 ]; then
    STAGED_DIFF="${STAGED_DIFF:0:80000}"$'\n\n[CATATAN: diff dipotong pada 80.000 karakter. Nilai HANYA yang terlihat di atas; JANGAN mengarang pelanggaran pada file/bagian yang tidak tampak.]'
fi

PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Standar Penyimpanan & Akses Berkas (Strict Backend Reviewer Laravel).
Periksa FULL Git Diff berikut secara KETAT terhadap aturan konsistensi berkas PUBLIK vs PRIVAT.
Penilaian MURNI oleh AI dari diff — hanya baris baru (+).

Konteks sistem:
- Berkas publik (foto profil, pengumuman, template) disimpan di disk publik.
- Berkas privat/sensitif (KTP, KK, ijazah, SK, e-file pegawai, lampiran cuti/izin,
  bukti kas/transaksi, selfie presensi) disimpan di disk privat.
- Service terpusat: App\Services\Storage\FileStorageService
  (store(..., private: true|false), url($path) [otomatis Signed URL bila privat],
   signedUrl($path)). URL Signed menunjuk endpoint generik GET /api/files/view.

Aturan Baku (STRICT):
1. Upload berkas privat WAJIB lewat FileStorageService::store($file, '<dir>', private: true).
   - SALAH: `Storage::disk('public')->put(...)`, `->putFileAs(...)`, `$file->store('...', 'public')`
     untuk berkas SENSITIF (KTP, KK, ijazah, SK, e-file, lampiran cuti/izin, bukti kas, selfie presensi).
   - BENAR: `$this->files->store($file, 'simpeg/dokumen_pegawai', private: true)`.
2. URL berkas privat WAJIB lewat FileStorageService::url()/signedUrl().
   - SALAH: `Storage::disk('public')->url($path)`, `Storage::url($path)`, atau menyusun URL
     r2.dev / R2_PRIVATE_URL / domain storage langsung untuk berkas privat.
   - BENAR: `app(FileStorageService::class)->url($path)` atau `->signedUrl($path)`.
3. DILARANG mengekspos URL storage langsung (r2.cloudflarestorage.com / pub-*.r2.dev) untuk
   berkas privat pada payload response JSON.
4. Endpoint yang menampilkan berkas privat WAJIB dilindungi (middleware `auth:api` ATAU
   middleware `signed` untuk URL sementara). DILARANG endpoint publik tanpa proteksi.
5. Berkas publik BOLEH memakai url() publik (tidak melanggar).

Catatan:
- HANYA periksa baris baru (+). Abaikan baris konteks.
- JANGAN menuduh simbol/import "tidak ada" (diff tidak memuat seluruh file).
- JANGAN menolak berkas yang jelas publik (foto profil, pengumuman, template).

Git Diff:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$STAGED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
PENTING: Jawab HANYA secara langsung tanpa memanggil tool atau membaca file.
Format Respon:
- Jika kode bersih dan memenuhi Standar Penyimpanan Berkas, jawab TEPAT: PASSED
- Jika ditemukan pelanggaran pada baris baru (+), awali respon dengan REJECTED dan rincian:
  * File & Potongan Baris Melanggar
  * Aturan yang Dilanggar
  * Alasan Penolakan
  * Solusi / Rekomendasi Perbaikan
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
    echo "❌ [Audit File Storage Standard] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit File Storage Standard] REJECTED oleh AI!"
    echo "================================ DETAIL TEMUAN AUDIT ================================"
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    echo "💡 Harap perbaiki seluruh pelanggaran di atas sebelum melakukan commit."
    exit 1
elif ! echo "$RESULT" | grep -qi "PASSED"; then
    echo "❌ [Audit File Storage Standard] REJECTED: AI tidak memberikan keputusan PASSED yang valid!"
    echo "================================ DETAIL OUTPUT ====================================="
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    exit 1
else
    echo "✅ [Audit File Storage Standard] PASSED (Berkas publik/privat konsisten)."
    exit 0
fi
