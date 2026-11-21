#!/bin/bash
# ==============================================================================
# AUDIT 03 (BE): API Documentation & Database Seeder Integrity (Unified AI Reviewer)
# ==============================================================================
# Memeriksa dengan AI:
# 1. Kelengkapan Dokumentasi API di docs/api/{Modul}/{Controller}.md & indeks docs/README.md
# 2. Database Seeder untuk tabel master/referensi baru (firstOrCreate/updateOrCreate)
# 3. Kontrak sync PDDIKTI: registry/seeder, queue job, count/progress, batch 500, persist per batch, tes, dokumentasi
# ==============================================================================

echo "📚 [Audit 3/3: Docs, Seeders & PDDIKTI Sync] Menilai dokumentasi API, seeder, dan kontrak sync PDDIKTI dengan AI..."

export PATH="$HOME/.local/bin:$HOME/.opencode/bin:/usr/local/bin:$PATH"
AI_ENGINE="${AI_ENGINE:-agy}"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/deepseek-v4.1-flash-free}"

DIFF_PATHS=(
    "app/Http/Controllers/**"
    "app/Services/**"
    "app/Jobs/**"
    "database/seeders/**"
    "database/migrations/**"
    "docs/**"
    "tests/Feature/**"
    ".agent/skills/**"
)
if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -M -- "${DIFF_PATHS[@]}")
else
    STAGED_DIFF=$(git diff --cached -M -- "${DIFF_PATHS[@]}")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit 3/3] Tidak ada perubahan backend/API, job, tes, docs, skill, migrasi, atau seeder yang diuji. Skip."
    exit 0
fi

if [ ${#STAGED_DIFF} -gt 100000 ]; then
    STAGED_DIFF="${STAGED_DIFF:0:100000}"$'\n\n[CATATAN: diff dipotong pada 100.000 karakter. Nilai HANYA yang terlihat di atas; JANGAN mengarang pelanggaran pada file yang tidak tampak.]'
fi

PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor AI khusus Dokumentasi API, Database Seeder, dan integrasi sinkronisasi PDDIKTI Laravel (Big Pickle).
Tugasmu menilai FULL Git Diff secara kontekstual dan ketat. Gunakan hanya bukti yang terlihat pada diff dan ikuti aturan berikut.

================================ STANDAR EVALUASI ================================

1. STANDAR DOKUMENTASI API:
   - Setiap controller baru atau endpoint yang ditambah/diubah WAJIB memiliki/memperbarui file dokumentasi di `docs/api/{Modul}/{Controller}.md` dan terdaftar pada tabel indeks `docs/README.md`.
   - Format docs WAJIB lengkap:
     * Header info: Modul, Base URL, Autentikasi, Status Dibuat/Diperbarui.
     * Tabel daftar endpoint: Method | Endpoint | Fungsi | Auth.
     * Request headers & Query parameters lengkap (default pagination: created_at, desc, per_page: 15, page: 1).
     * Request body (jika ada) dan contoh JSON response nyata (200/201, pagination, serta error 400/401/403/404/422).
     - Untuk kontrak Pengaturan API: dokumentasikan boolean `token_configured` dan tegaskan token mentah tidak pernah dikembalikan; jelaskan bahwa field token yang tidak dikirim/null/kosong mempertahankan kredensial tersimpan.
     - Jika ada aksi sync/import: dokumentasikan bahwa Uji Koneksi tidak menulis target dan Sync menjalankan impor; format respons counts (fetched/created/updated/skipped), format sumber yang didukung, perilaku idempotensi/mapping, kebijakan skip relasi tak valid, pagination cap, dan contoh error upstream.

2. STANDAR DATABASE SEEDER & MIGRATION:
   - Data awal / master data pada seeder WAJIB menggunakan `updateOrCreate` atau `firstOrCreate` (DILARANG `::create()` mentah yang gagal jika di-seed ulang).
   - Seeder idempoten (bisa dijalankan berkali-kali tanpa duplicate entry error).
   - Seeder master data baru WAJIB didaftarkan ke `DatabaseSeeder.php` atau koordinator modul terkait.

3. STANDAR SINKRONISASI IMPORT PDDIKTI NEO FEEDER (WAJIB JIKA DIFF MENAMBAH/MENGUBAH INTEGRASI IMPORT PDDIKTI):
    Gunakan pola `SyncPddiktiMahasiswaJob` dan `SiakadMahasiswaIntegrationSyncService` sebagai referensi arsitektur. Terapkan aturan domain entitas terkait, jangan menyalin mapping mahasiswa.
    - Registry integrasi tetap WAJIB didaftarkan secara idempoten melalui `IamApiIntegrationSeeder`, dengan action, target_entity, source schema dan metadata count bila action count tersedia. Pertahankan setting koneksi admin saat seeder dijalankan ulang.
    - Uji Koneksi harus read-only dan mengambil sampel terbatas; ia tidak boleh menulis target atau menjalankan import.
    - Setiap sync tulis harus masuk queue melalui job yang benar-benar mengimplementasikan `ShouldQueue`. Request hanya validasi, lock, catat progres queued, dispatch setelah commit, lalu merespons ID progres. Import/paging panjang tidak boleh berjalan sinkron di request HTTP.
    - Periksa ketersediaan dan hak akses action hitung total untuk entitas: variasi nyata mengikuti katalog Feeder, misalnya `GetEntitasCount` atau `GetCountEntitas`. Jangan mengarang action. Bila tersedia, verifikasi responsnya, daftarkan pada registry dan gunakan untuk total/progress. Bila tidak tersedia, diff/docs/tests harus menyatakan keterbatasan dan menunjukkan progress indeterminate tanpa persentase/ETA palsu.
    - Semua request list memakai `limit` tepat 500, offset deterministik, dan batas halaman eksplisit. Batas maksimum yang tercapai tanpa akhir terverifikasi harus gagal dengan jelas.
    - Setiap batch harus divalidasi dan dipersist ke database sebelum mengambil batch selanjutnya. Jangan menampung seluruh populasi sumber di memori. Transaksi dan progress dilakukan per batch; progress bertambah setelah commit. Pertahankan idempotensi, validasi relasi eksplisit, jangan tebak relasi, dan jangan hapus record yang absen dari respons sumber.
    - Job wajib menangani sukses/gagal, status integrasi/progress, lock yang dibersihkan pada semua jalur terminal, log aman, detail error terbatas, dan identitas actor/integrasi.
    - Feature test wajib membuktikan registry idempoten, Uji Koneksi read-only, dispatch ke queue, limit/offset 500, count atau fallback progress indeterminate, persist per batch sebelum request batch berikutnya, idempotensi, progress setelah persist, serta cleanup lock/upstream failure.
    - Kontrak `docs/specs/api-contract.md` dan dokumentasi API/indeks harus menjelaskan action count yang diverifikasi atau ketidaktersediaannya, queue/progress, batch 500, persistensi per batch, pagination cap, mapping, idempotensi, skip, dan error upstream.
    - Rubrik ini berlaku hanya bila diff menambah/mengubah integrasi import PDDIKTI; jangan mewajibkan job/import pada perubahan yang hanya berupa tes koneksi read-only atau dokumentasi yang tidak mengubah sync.

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
if command -v agy &> /dev/null; then
    RESULT=$(timeout 180s agy --model gemini-3.8-flash-low --disable-slash-commands --print "$(cat "$PROMPT_FILE")" < /dev/null 2>&1)
    AI_EXIT_CODE=$?
fi

if [ $AI_EXIT_CODE -ne 0 ] && [ -x "$OPENCODE_BIN" ]; then
    RESULT=$(timeout 90s "$OPENCODE_BIN" run -m "$MODEL" "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
fi

rm -f "$PROMPT_FILE"

if [ $AI_EXIT_CODE -ne 0 ]; then
    echo "❌ [Audit 3/3: Docs, Seeders & PDDIKTI Sync] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    echo "Detail output: $RESULT"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit 3/3: Docs, Seeders & PDDIKTI Sync] REJECTED oleh AI!"
    echo "================================ DETAIL TEMUAN AUDIT ================================"
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    echo "💡 Harap lengkapi dokumentasi API atau seeder di atas sebelum melakukan commit."
    exit 1
elif ! echo "$RESULT" | grep -qi "PASSED"; then
    echo "⚠️ [Audit 3/3: Docs, Seeders & PDDIKTI Sync] Output AI tidak dikenali:"
    echo "$CLEAN_RESULT"
    exit 1
fi

echo "✅ [Audit 3/3: Docs, Seeders & PDDIKTI Sync] PASSED (Divalidasi AI reviewer)."
exit 0
