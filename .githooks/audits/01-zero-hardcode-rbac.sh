#!/bin/bash
# ==============================================================================
# AUDIT 01: Zero Hardcode & RBAC Reviewer (BE) — AI Muse Spark Strict (Full Diff, tanpa regex)
# ==============================================================================

echo "🤖 [Audit 2/9: Zero Hardcode & RBAC] Memeriksa perubahan dengan AI (AI Muse Spark 1.3)..."

export PATH="$HOME/.local/bin:$HOME/.opencode/bin:/usr/local/bin:$PATH"
AI_ENGINE="${AI_ENGINE:-agy}"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "app/**" "routes/**")
else
    STAGED_DIFF=$(git diff --cached -- "app/**" "routes/**")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit Zero Hardcode & RBAC] Tidak ada perubahan app/routes yang diuji. Skip."
    exit 0
fi

# DEEP AI AUDIT
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Zero Hardcode & RBAC Backend Laravel (Strict Reviewer, AI Muse Spark 1.3).
Periksa FULL Git Diff berikut secara SANGAT KETAT terhadap seluruh aturan Zero Hardcode & RBAC. Penilaian MURNI oleh AI dari diff — tidak ada pre-check regex.

Aturan Baku (STRICT — setiap aturan bernomor, nilai hanya dari baris baru):
1. DILARANG `in:STATIS` untuk data master/dropdown dinamis, WAJIB `exists:nama_tabel,id`.
   - SALAH: `'jalur_masuk' => 'required|in:REGULER,KARYAWAN'` atau `'agama' => 'in:Islam,Kristen'` (data master referensi/dropdown dinamis).
   - BENAR: `'jalur_masuk_id' => 'required|exists:spmb_master_referensi,id'` atau `'tipe_id' => 'required|exists:core_tipe_referensi,id'`.
   - Nilai `in:` yang sah adalah sort direction (`'sort_order' => 'in:asc,desc'`) dan enum aksi workflow status internal (`in:disetujui,ditolak`).
   - SALAH bila memakai `exists:nama_tabel,kode` atau varian kolom lain — WAJIB `exists:nama_tabel,id`.
2. DILARANG kata `user_type` di MANA PUN pada baris baru (+).
   - Berlaku untuk: $fillable model User, validasi Form Request, where/select/query builder, if/else/switch/match, payload response JSON, factory/seeder.
   - SALAH: `protected $fillable = [..., 'user_type'];`, `'user_type' => 'required|in:admin,mahasiswa'`, `User::where('user_type','admin')->get()`, `if ($user->user_type === 'admin')`, `return response()->json(['user_type' => $user->user_type])`, `'user_type' => 'admin'` di factory/seeder.
   - BENAR: andalkan relasi `$user->roles()` / `$user->permissions()`, dan di seeder pakai `$user->roles()->attach($roleId)`.
3. DILARANG banding string slug modul/role dalam branching; relasi/filter pakai ID entitas.
   - SALAH: `if ($role === 'spmb')`, `if ($user->role == 'admin')`, `$q->where('modul','sikeu')`, `match($tipe){ 'mahasiswa' => ... }` untuk menentukan akses/relasi.
   - BENAR: `where('module_id', $moduleId)`, `where('role_id', $roleId)`, `$user->hasRole('admin')`, `$user->hasPermission('spmb.create')` (lihat aturan 5 untuk klarifikasi string argumen helper).
4. WAJIB otorisasi via Gate/Policy/RBAC, bukan perbandingan string manual.
   - WAJIB memakai salah satu: `$this->authorize()`, `Gate::authorize()`, `$user->hasRole()`, `$user->hasPermission()`, middleware `can:`.
   - SALAH: `if (auth()->user()->user_type !== 'admin') abort(403);`.
   - BENAR: `$this->authorize('viewAny', User::class);`, `Gate::authorize('approve-krs');`, `Route::middleware(['auth:sanctum','can:manage-users'])`, `if (!auth()->user()->hasRole('admin')) abort(403);`.
   - Gate WAJIB didefinisikan di `app/Providers/AppServiceProvider.php` (Gate::define / Gate::before).
   - Helper WAJIB: `hasPermission(string $permissionSlug)` dan `hasRole(string $roleSlug)` memakai relasi `belongsToMany(Role::class,'user_roles')->withPivot(['valid_from','valid_until'])`.
   - Slug permission WAJIB format `{modul}.{aksi}` ATAU `{modul}.{submodul}.{aksi}` (proyek ini memang memakai slug 3-segmen di database, contoh RESMI: `spmb.manage`, `spmb.laporan.read`, `spmb.laporan.export`, `iam.roles.read`). DILARANG hanya memakai slug yang TIDAK ADA di `database/seeders/IAM/PermissionSeeder.php`. JANGAN menolak slug yang terdaftar di PermissionSeeder.
     - SALAH: `readUsers`, `approve`, `users` (tanpa aksi), atau slug karangan yang tidak terdaftar di PermissionSeeder.
     - BENAR: `users.read`, `krs.approve`, `spmb.manage`, `spmb.laporan.read` (semua ada di PermissionSeeder).
   - Respons auth (login/SSO/AuthController) WAJIB eager-load relasi roles via `with('roles')` atau `$user->load('roles')` agar frontend dapat mengevaluasi otorisasi.
5. WAJIB pahami klarifikasi: string literal di dalam argumen `hasRole()` / `hasPermission()` adalah BENAR, bukan pelanggaran.
   - BENAR (jangan tolak): `$user->hasRole('admin')`, `$user->hasPermission('users.read')`, `$q->where('slug',$permissionSlug)` di dalam definisi helper/Gate/Policy.
   - SALAH (tetap tolak): `$user->user_type === 'admin'`, `where('user_type','admin')`, `if ($modul === 'spmb')`.

Catatan:
- HANYA periksa baris baru (+) — baris konteks tanpa `+` dan file yang tidak diubah WAJIB diabaikan. Jangan menolak kode lama.

- JANGAN menuduh sebuah simbol/komponen/ikon "tidak di-import" atau "tidak terdefinisi": diff hanya memuat potongan file, sehingga baris import sering berada DI LUAR diff. Validitas import sudah diverifikasi terpisah (tsc --noEmit untuk FE, php -l untuk BE). Laporkan hanya pelanggaran yang benar-benar terlihat pada baris (+).

Git Diff:
EOF

echo '```diff' >> "$PROMPT_FILE"
echo "$STAGED_DIFF" >> "$PROMPT_FILE"
echo '```' >> "$PROMPT_FILE"

cat << 'EOF' >> "$PROMPT_FILE"
PENTING: Jawab HANYA secara langsung tanpa memanggil tool atau membaca file.
Format Respon:
- Jika kode bersih dan memenuhi aturan di atas, jawab TEPAT: PASSED
- Jika ditemukan pelanggaran pada baris baru (+), awali respon dengan REJECTED dan berikan rincian lengkap:
  * File & Potongan Baris Melanggar: (nama file dan baris/kode yang melanggar)
  * Aturan yang Dilanggar: (nama aturan / standar yang dilanggar)
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
    echo "❌ [Audit Zero Hardcode & RBAC] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit Zero Hardcode & RBAC] REJECTED oleh AI (Muse Spark)!"
    echo "================================ DETAIL TEMUAN AUDIT ================================"
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    echo "💡 Harap perbaiki seluruh pelanggaran di atas sebelum melakukan commit."
    exit 1
elif ! echo "$RESULT" | grep -qi "PASSED"; then
    echo "❌ [Audit Zero Hardcode & RBAC] REJECTED: AI tidak memberikan keputusan PASSED yang valid!"
    echo "================================ DETAIL OUTPUT ====================================="
    echo "$CLEAN_RESULT"
    echo "===================================================================================="
    exit 1
else
    echo "✅ [Audit Zero Hardcode & RBAC] PASSED (Divalidasi AI Muse Spark 1.3)."
    exit 0
fi
