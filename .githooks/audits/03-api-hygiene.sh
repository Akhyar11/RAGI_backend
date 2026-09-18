#!/bin/bash
# ==============================================================================
# AUDIT 03: API Hygiene Reviewer (BE) — AI Muse Spark Strict (Full Diff, tanpa regex)
# ==============================================================================

echo "🧹 [Audit 4/9: API Hygiene] Memeriksa perubahan dengan AI (AI Muse Spark 1.3)..."

export PATH="$HOME/.local/bin:$HOME/.opencode/bin:/usr/local/bin:$PATH"
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

# DEEP AI AUDIT
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus API Hygiene Backend Laravel (Strict Backend Reviewer, AI Muse Spark 1.3).
Periksa FULL Git Diff berikut secara SANGAT KETAT terhadap seluruh aturan hygiene + error handling. Penilaian MURNI oleh AI dari diff — tidak ada pre-check regex.

Aturan Baku (STRICT — setiap aturan bernomor, nilai hanya dari baris baru):
1. WAJIB format error `{status,message,errors?}`; DILARANG format liar.
   - SALAH: `return response()->json(['data'=>$e->getMessage()])`, error validasi tanpa `errors`, error non-422 memakai `errors`, HTML/stack trace mentah bocor.
   - BENAR: `{"status":"error","message":"..."}`, validasi 422 memakai `{"status":"error","message":"Data yang diberikan tidak valid.","errors":{"field":["..."]}}` (`errors` HANYA untuk 422).
2. WAJIB global handler di `bootstrap/app.php` via `withExceptions`; DILARANG handler hilang/salah kode.
   - SALAH: tidak ada render ValidationException→422, ModelNotFound→404, Authorization→403, Authentication→401, Throwable→500.
   - BENAR: `ValidationException→422`, `ModelNotFoundException→404 ("{Model} tidak ditemukan.")`, `AuthorizationException→403 ("Anda tidak memiliki izin...")`, `AuthenticationException→401 ("Token tidak valid...")`, `Throwable→500 + Log::error + hanya bila !config('app.debug')` dengan pesan `Terjadi kesalahan internal pada server.`.
3. WAJIB tabel kode tepat; DILARANG kode sembarang.
   - SALAH: create balas 200, validasi balas 400/500, unauthorized balas 403, forbidden balas 401.
   - BENAR: `200`=GET/PUT/PATCH/DELETE ok, `201`=POST create, `400`=bad request, `401`=belum login/token kedaluwarsa, `403`=tanpa izin, `404`=not found, `422`=validasi gagal, `429`=rate limit, `500`=unexpected.
4. DILARANG try-catch umum di controller; WAJIB Log::error untuk 500; WAJIB APP_DEBUG=false produksi.
   - SALAH: `try { ... } catch (\\Exception $e) { return response()->json([...], 500); }` menelan exception umum yang seharusnya ditangani global handler; `catch` 500 tanpa `\\Log::error($e)`.
   - BENAR: biarkan exception umum ke global handler; `try-catch` HANYA untuk exception bisnis spesifik yang perlu respons khusus; setiap 500 WAJIB `\\Log::error($e)`; produksi `APP_DEBUG=false`.
5. DILARANG sisa debug pada baris baru; WAJIB bersih.
   - SALAH: `+dd($x);`, `+dump($data);`, `+var_dump($x);`, `+print_r($x);`, `+ray($x);`, `+die();`, `+exit;`, `+eval($code);`.
   - BENAR: hapus seluruh debug sebelum commit; gunakan `Log::debug()` bila perlu jejak non-produksi.
6. DILARANG `env()/getenv()/$_ENV` langsung di `app/`; WAJIB `config()/SystemSetting`.
   - SALAH: `+env('DB_HOST')`, `+getenv('KEY')`, `+$_ENV['KEY']` di Controller/Model/Service dalam `app/`.
   - BENAR: `config('database.host')`, `config('app.key')`, atau baca `SystemSetting` dari DB; `env()` hanya boleh di file `config/*.php`.

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
- Jika kode bersih dan memenuhi standar API Hygiene, jawab TEPAT: PASSED
- Jika ditemukan pelanggaran pada baris baru (+), awali respon dengan REJECTED dan berikan rincian lengkap:
  * File & Potongan Baris Melanggar: (nama file dan baris/kode yang melanggar)
  * Aturan yang Dilanggar: (nama aturan / standar API Hygiene yang dilanggar)
  * Alasan Penolakan: (penjelasan detail mengapa kode tersebut melanggar)
  * Solusi / Rekomendasi Perbaikan: (solusi konkrit atau contoh kode perbaikan)
EOF

AI_EXIT_CODE=1
if [ "$AI_ENGINE" != "agy" ] && [ -x "$OPENCODE_BIN" ]; then
    RESULT=$(timeout 20s "$OPENCODE_BIN" run --pure -m "$MODEL" "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
fi

if [ $AI_EXIT_CODE -ne 0 ] && command -v agy &> /dev/null; then
    RESULT=$(timeout 30s agy --model gemini-3.8-flash-low --print "$(cat "$PROMPT_FILE")" 2>&1)
    AI_EXIT_CODE=$?
fi

rm -f "$PROMPT_FILE"

if [ $AI_EXIT_CODE -ne 0 ]; then
    echo "❌ [Audit API Hygiene] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit API Hygiene] REJECTED oleh AI (Muse Spark)!"
    echo "================================ DETAIL TEMUAN AUDIT ================================"
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    echo "💡 Harap perbaiki seluruh pelanggaran di atas sebelum melakukan commit."
    exit 1
elif ! echo "$RESULT" | grep -qi "PASSED"; then
    echo "❌ [Audit API Hygiene] REJECTED: AI tidak memberikan keputusan PASSED yang valid!"
    echo "================================ DETAIL OUTPUT ====================================="
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    exit 1
else
    echo "✅ [Audit API Hygiene] PASSED (Divalidasi AI Muse Spark 1.3)."
    exit 0
fi
