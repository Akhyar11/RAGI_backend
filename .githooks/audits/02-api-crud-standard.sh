#!/bin/bash
# ==============================================================================
# AUDIT 02: API CRUD Standard Reviewer (BE) — AI Muse Spark Strict (Full Diff, tanpa regex)
# ==============================================================================

echo "🤖 [Audit 3/9: API CRUD Standard] Memeriksa perubahan dengan AI (AI Muse Spark 1.3)..."

export PATH="$HOME/.local/bin:$HOME/.opencode/bin:/usr/local/bin:$PATH"
AI_ENGINE="${AI_ENGINE:-agy}"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "app/Http/Controllers/**" "app/Http/Requests/**" "app/Services/**")
else
    STAGED_DIFF=$(git diff --cached -- "app/Http/Controllers/**" "app/Http/Requests/**" "app/Services/**")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit API CRUD Standard] Tidak ada perubahan controller/request/service yang diuji. Skip."
    exit 0
fi

# DEEP AI AUDIT
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus API CRUD Standard Laravel (Strict Backend Reviewer, AI Muse Spark 1.3).
Periksa FULL Git Diff berikut secara SANGAT KETAT terhadap seluruh aturan API CRUD + Service Layer + Naming. Penilaian MURNI oleh AI dari diff — tidak ada pre-check regex.

Aturan Baku (STRICT — setiap aturan bernomor, nilai hanya dari baris baru):
1. WAJIB envelope JSON konsisten; DILARANG envelope salah.
   - SALAH: list tanpa `meta`/`filters`, single dengan `meta`, error 422 tanpa `errors`, error non-422 memakai `errors`.
   - BENAR List: `{"status":"success","message":"...","data":[...],"meta":{"current_page":1,"per_page":15,"total":100,"last_page":7,"from":1,"to":15},"filters":{"search":"...","sort_by":"created_at","sort_order":"desc"}}`.
   - BENAR Single (create/update/show): `{"status":"success","message":"...","data":{...}}` TANPA `meta`.
   - BENAR Error: `{"status":"error","message":"...","errors":{...}}` dengan `errors` HANYA untuk 422.
2. WAJIB paginate pada index; DILARANG `->get()`/`->all()` tanpa limit di data tabel dinamis.
   - SALAH: `$data = $query->get(); return response()->json(['data'=>$data]);`.
   - BENAR: `$perPage = min(100, $request->integer('per_page', 15)); $data = $query->paginate($perPage);` lalu kembalikan `data=>$data->items()` + `meta` (current_page, per_page, total, last_page, from, to).
   - WAJIB default `per_page` 15 dan maks 100 via `$request->integer()+min`.
3. WAJIB search + sort_by whitelist + sort_order pada index.
   - SALAH: index tanpa `search`, `orderBy($request->sort_by)` langsung tanpa whitelist, sort default selain `created_at`/`desc`.
   - BENAR: `if ($request->filled('search')) { $query->where(fn...) }`, `$allowed=['created_at','updated_at','nama']; $sortBy=in_array($request->sort_by,$allowed)?$request->sort_by:'created_at'; $sortOrder=$request->sort_order==='asc'?'asc':'desc'; $query->orderBy($sortBy,$sortOrder);`.
4. WAJIB Form Request Store*/Update*Request di `app/Http/Requests`; DILARANG validasi inline.
   - SALAH: `$request->validate([...])` atau `Validator::make(...)` di Controller.
   - BENAR: `php artisan make:request StoreItemRequest`, `public function store(StoreItemRequest $request)`, `public function update(UpdateItemRequest $request, Model $m)`.
5. WAJIB kode HTTP tepat & delegasi error ke Global Exception Handler; DILARANG 500 manual tanpa Log.
   - SALAH: catch manual 500 tanpa Log::error, POST create balas 200, validasi gagal balas 500, not-found balas 200.
   - BENAR: 200 (GET/PUT/PATCH/DELETE ok), 201 (POST create), 400 (bad request), 403 (forbidden), 404 (not found), 422 (validasi), dan error tak terduga didelegasikan ke global exception handler bootstrap/app.php (jangan menelan error dengan try-catch umum di controller).
6. WAJIB SoftDeletes: respons destroy + endpoint restore opsional.
   - SALAH: model pakai `SoftDeletes` tapi `destroy` tidak menjelaskan soft-delete / tidak ada `POST /resource/{id}/restore` saat dibutuhkan.
   - BENAR: `use SoftDeletes;`, `destroy` mengembalikan status soft-delete, opsional `POST /resource/{id}/restore`.
7. WAJIB route dilindungi `auth:sanctum`.
   - SALAH: `Route::apiResource('users', ...)` tanpa middleware auth.
   - BENAR: `Route::middleware(['auth:sanctum'])->group(...)` atau `Route::apiResource(...)->middleware('auth:sanctum')`.
8. WAJIB Service layer bila logika > CRUD sederhana; DILARANG pola controller gemuk.
   - Wajib buat Service di `app/Services/{Modul}/` bila: >1 operasi DB, cabang if/else kompleks, reuse >1 tempat, butuh transaksi.
   - SALAH: controller berisi banyak query/transaksi, Service menerima `$request`, multi-tulis tanpa `DB::transaction`.
   - BENAR: `namespace App\Services\IAM; class UserService { public function create(array $data): User { return DB::transaction(fn()=>...); } }`, inject `public function __construct(private UserService $userService){}`, panggil `$this->userService->create($request->validated())`, unit test di `tests/Unit/Services/`.
   - DILARANG parameter Request $request pada method Service — data dioper via `array $data` (pemanggilan helper AuditLogService::record dengan request: request() diperbolehkan).
   - WAJIB `DB::transaction` untuk multi-tulis.
9. WAJIB naming standard; DILARANG verb/snake di URL, camelCase di JSON, boolean/waktu tak standar.
   - SALAH: `/api/get-users`, `/api/create-permission`, `/api/Users`, `/api/role_permissions`, `GET /api/roles-by-user/{id}`, `POST /api/login-auth`, JSON `{"firstName":"...","active":true,"created":"..."}`, boolean `active/verified`, waktu `login_time/tanggal_lahir`.
   - BENAR: kebab-case plural tanpa verb `/api/users`, `/api/audit-logs`, `/api/role-permissions`; nested `GET /api/users/{id}/roles`; custom action di ujung `PATCH /api/users/{id}/status`, `POST /api/auth/login`; `PUT` utuh vs `PATCH` parsial; JSON snake_case `first_name/is_active/created_at`; boolean prefix `is_/has_/can_` (`is_active`,`has_access`); waktu `_at/_date` (`created_at`,`last_login_at`,`birth_date`); prefix `/api/admin|/api/auth|/api/[Modul]` (`/api/spmb`,`/api/siakad`).

Catatan:
- HANYA periksa baris baru (+) — baris konteks tanpa `+` WAJIB diabaikan.

- JANGAN menuduh sebuah simbol/komponen/ikon "tidak di-import" atau "tidak terdefinisi": diff hanya memuat potongan file, sehingga baris import sering berada DI LUAR diff. Validitas import sudah diverifikasi terpisah (tsc --noEmit untuk FE, php -l untuk BE). Laporkan hanya pelanggaran yang benar-benar terlihat pada baris (+).

Git Diff:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$STAGED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
PENTING: Jawab HANYA secara langsung tanpa memanggil tool atau membaca file.
Format Respon:
- Jika kode bersih dan memenuhi API CRUD Standard, jawab TEPAT: PASSED
- Jika ditemukan pelanggaran pada baris baru (+), awali respon dengan REJECTED dan berikan rincian lengkap:
  * File & Potongan Baris Melanggar: (nama file dan baris/kode yang melanggar)
  * Aturan yang Dilanggar: (nama aturan / standar API CRUD yang dilanggar)
  * Alasan Penolakan: (penjelasan detail mengapa kode tersebut melanggar)
  * Solusi / Rekomendasi Perbaikan: (solusi konkrit atau contoh kode perbaikan)
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
    echo "❌ [Audit API CRUD Standard] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit API CRUD Standard] REJECTED oleh AI (Muse Spark)!"
    echo "================================ DETAIL TEMUAN AUDIT ================================"
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    echo "💡 Harap perbaiki seluruh pelanggaran di atas sebelum melakukan commit."
    exit 1
elif ! echo "$RESULT" | grep -qi "PASSED"; then
    echo "❌ [Audit API CRUD Standard] REJECTED: AI tidak memberikan keputusan PASSED yang valid!"
    echo "================================ DETAIL OUTPUT ====================================="
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    exit 1
else
    echo "✅ [Audit API CRUD Standard] PASSED (Divalidasi AI Muse Spark 1.3)."
    exit 0
fi
