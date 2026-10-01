#!/bin/bash
# ==============================================================================
# AUDIT 02 (BE): Backend Architecture, Standards & Security (Unified AI Reviewer)
# ==============================================================================
# Memeriksa dalam 1 kali evaluasi AI terpadu:
# 1. Zero Hardcode & RBAC Policy (permission slug resmi, otorisasi controller, dynamic reference)
# 2. API CRUD Standard (envelope {status, message, data, meta}, per_page, sort_order, status codes)
# 3. Audit Log Standard (pencatatan mutasi sensitif, try-catch, old/new values)
# 4. File Storage Standard (FileStorageService, private signed URLs, tidak ada storage URL mentah)
# 5. Table Naming Standard (prefix modul baku: iam_, spmb_, siakad_, sikeu_, simpeg_, dll.)
# ==============================================================================

echo "🤖 [Audit 2/3: Architecture, Standards & Security] Memeriksa kepatuhan arsitektur backend dengan AI (AI Muse Spark 1.3)..."

export PATH="$HOME/.local/bin:$HOME/.opencode/bin:/usr/local/bin:$PATH"
AI_ENGINE="${AI_ENGINE:-agy}"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "app/**" "routes/**" "database/migrations/**")
else
    STAGED_DIFF=$(git diff --cached -- "app/**" "routes/**" "database/migrations/**")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit 2/3] Tidak ada perubahan backend (app, routes, migrations) yang diuji. Skip."
    exit 0
fi

# Batasi ukuran diff bila terlalu besar untuk menjaga batas token & performa
if [ ${#STAGED_DIFF} -gt 100000 ]; then
    STAGED_DIFF="${STAGED_DIFF:0:100000}"$'\n\n[CATATAN: diff dipotong pada 100.000 karakter. Nilai HANYA yang terlihat di atas; JANGAN mengarang pelanggaran pada file yang tidak tampak.]'
fi

PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Senior Principal Code Auditor khusus Ekosistem Backend Laravel (AI Muse Spark 1.3).
Tugasmu adalah memeriksa FULL Git Diff backend berikut secara SANGAT KETAT terhadap 5 Standar Inti Arsitektur & Keamanan.
Evaluasi HANYA baris baru (+) yang ditambahkan atau diubah. Abaikan baris konteks tanpa tanda (+).

================================ STANDAR INTI EVALUASI ================================

1. STANDAR RBAC & ZERO HARDCODE (KEBIJAKAN KETAT):
   - WAJIB slug permission resmi yang selaras dengan `PermissionSeeder.php` (mis: `spmb.manage`, `spmb.pendaftaran.read`, `spmb.pendaftaran.create`, dll). Dilarang membuat slug karangan (mis: `.view`).
   - WAJIB otorisasi hak akses pada SEMUA aksi controller (via `$this->authorize(...)`, `$request->user()->hasPermission(...)`, FormRequest `authorize()`, atau middleware `can:`). Dilarang membiarkan aksi controller tanpa pemeriksaan otorisasi.
   - Dilarang hardcode string entitas master atau opsi statis yang seharusnya mereferensikan tabel database via ID.

2. STANDAR API CRUD & RESPONSE ENVELOPE:
   - WAJIB envelope JSON standar:
     * Sukses list/detail: `{"status": "success", "message": "...", "data": ..., "meta": {...}}` (meta untuk pagination).
     * Sukses delete: `{"status": "success", "message": "...", "data": null}`.
     * Error: `{"status": "error", "message": "..."}`. Error validasi 422: `{"status": "error", "message": "...", "errors": {...}}`.
   - WAJIB parameter pagination baku `per_page` (dengan batas maksimum aman `min(100, $request->integer('per_page', 15))`).
   - WAJIB sorting `sort_order` bernilai `asc` atau `desc` (mis: `$request->sort_order === 'asc' ? 'asc' : 'desc'`).
   - WAJIB kode HTTP yang tepat: 200 (OK), 201 (Created), 400 (Bad Request / Precondition gagal), 401 (Unauthenticated), 403 (Unauthorized / Tanpa izin), 404 (Not Found), 422 (Validation Error), 500 (Server Error).

3. STANDAR AUDIT LOG:
   - WAJIB mencatat perubahan atau aksi sensitif (create, update, delete, approve, reject, export/download dokumen) menggunakan `AuditLogService::record(...)`.
   - WAJIB dibungkus dalam blok `try { AuditLogService::record(...); } catch (\Throwable $e) { Log::warning(...); }` agar kegagalan log tidak menggagalkan request utama.
   - Pada aksi update/delete: ambil `$oldValues = $model->getOriginal()` SEBELUM aksi save/update/delete, dan ambil `$newValues = $model->getChanges()` SETELAH save/update.

4. STANDAR FILE STORAGE (PUBLIC VS PRIVATE):
   - Berkas privat/sensitif (KTP, KK, ijazah, SK kelulusan, dokumen pegawai, e-file) WAJIB disimpan dengan `FileStorageService::store($file, 'dir', private: true)`.
   - URL berkas privat WAJIB diakses via Signed URL melalui `FileStorageService::url()` atau `FileStorageService::signedUrl()`. DILARANG KERAS mengekspos URL storage langsung (`r2.dev`, `Storage::disk('public')->url()`).

5. STANDAR DATABASE TABLE & MODEL NAMING:
   - Seluruh tabel database baru (`Schema::create`) dan Model (`protected $table`) WAJIB menggunakan prefix modul resmi (`iam_`, `spmb_`, `siakad_`, `sikeu_`, `simpeg_`, `sinapra_`, `sippm_`, `lms_`, `core_`, `oauth_`, `upm_`).
   - Foreign key wajib merujuk ke tabel yang berprefix.

========================================================================================

Catatan Penilaian:
- HANYA periksa baris baru (+) — baris tanpa `+` WAJIB diabaikan.
- JANGAN menuduh simbol/kelas/import tidak terdefinisi jika baris `use` berada di luar potongan diff. Validitas sintaks telah diuji terpisah via php -l.
- Jika ada kekurangan atau pelanggaran nyata pada baris baru (+), berikan penolakan yang spesifik dan solutif.

Git Diff:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$STAGED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
PENTING: Jawab HANYA secara langsung tanpa memanggil tool atau membaca file.
Format Respon:
- Jika kode bersih dan memenuhi kelima standar arsitektur di atas, jawab TEPAT: PASSED
- Jika ditemukan pelanggaran pada baris baru (+), awali respon dengan REJECTED dan berikan rincian:
  * File & Potongan Baris Melanggar: (nama file dan baris/kode yang melanggar)
  * Standar yang Dilanggar: (nama standar yang dilanggar)
  * Alasan Penolakan: (penjelasan detail)
  * Solusi / Rekomendasi Perbaikan: (contoh kode perbaikan yang konkret)
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
    echo "❌ [Audit 2/3: Architecture, Standards & Security] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit 2/3: Architecture, Standards & Security] REJECTED oleh AI!"
    echo "================================ DETAIL TEMUAN AUDIT ================================"
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    echo "💡 Harap perbaiki seluruh pelanggaran di atas sebelum melakukan commit."
    exit 1
elif ! echo "$RESULT" | grep -qi "PASSED"; then
    echo "⚠️ [Audit 2/3: Architecture, Standards & Security] Output AI tidak dikenali:"
    echo "$CLEAN_RESULT"
    exit 1
fi

echo "✅ [Audit 2/3: Architecture, Standards & Security] PASSED (Divalidasi AI Muse Spark 1.3)."
exit 0
