#!/bin/bash
# ==============================================================================
# AUDIT 01: Zero Hardcode & RBAC Reviewer (BE) — STRICT HYBRID (Regex + AI Muse)
# ==============================================================================

echo "🤖 [Audit 2/9: Zero Hardcode & RBAC] Memeriksa perubahan dengan AI (Opencode Muse)..."

export PATH="$HOME/.opencode/bin:/usr/local/bin:$PATH"
OPENCODE_BIN=$(command -v opencode || echo "$HOME/.opencode/bin/opencode")
MODEL="${OPENCODE_MODEL:-opencode/muse-spark-1.3-contributor-free}"

if [ -n "$DIFF_TARGET" ]; then
    STAGED_DIFF=$(git diff "$DIFF_TARGET" -- "app/**" "routes/**")
    STAGED_FILES=$(git diff "$DIFF_TARGET" --name-only --diff-filter=ACM -- "app/**/*.php" "app/*.php" "routes/*.php")
else
    STAGED_DIFF=$(git diff --cached -- "app/**" "routes/**")
    STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM -- "app/**/*.php" "app/*.php" "routes/*.php")
fi

if [ -z "$STAGED_DIFF" ]; then
    echo "ℹ️ [Audit Zero Hardcode & RBAC] Tidak ada perubahan app/routes yang diuji. Skip."
    exit 0
fi

# ------------------------------------------------------------------------------
# 1. DETERMINISTIC PRE-CHECK (Fast Regex Rejection)
# ------------------------------------------------------------------------------
FAILED_REGEX=0

while IFS= read -r file; do
    [ -f "$file" ] || continue
    if [ -n "$DIFF_TARGET" ]; then
        ADDED=$(git diff "$DIFF_TARGET" -- "$file" | grep '^+' | grep -v '^+++' | sed 's^+^^')
    else
        ADDED=$(git diff --cached -- "$file" | grep '^+' | grep -v '^+++' | sed 's^+^^')
    fi
    [ -z "$ADDED" ] && continue

    # 1. Validasi enum statis: in:XXX dengan huruf kapital
    ENUM_HIT=$(echo "$ADDED" | grep -oP 'in:\K[A-Za-z0-9_|,\s.-]+' | grep -P '([A-Z]{2,}|[A-Z][a-z]{2,})' | head -n 3)
    if [ -n "$ENUM_HIT" ]; then
        echo "❌ [Audit Zero Hardcode] Validasi enum statis di $file:"
        echo "$ENUM_HIT" | sed 's/^/    in:/'
        echo "   💡 Validasi dropdown/master WAJIB memakai exists:nama_tabel,id (bukan in:STATIS)."
        FAILED_REGEX=1
    fi

    # 2. Perbandingan user_type statis dalam logika
    USERTYPE_HIT=$(echo "$ADDED" | grep -P '(==|===|!=|!==|in_array|match\s*\(|^\s*case\s)' | grep -P 'user.type' | head -n 3)
    if [ -n "$USERTYPE_HIT" ]; then
        echo "❌ [Audit Zero Hardcode] Perbandingan user_type statis di $file:"
        echo "$USERTYPE_HIT" | sed 's/^/    /'
        echo "   💡 Otorisasi WAJIB via Policy/Gate atau hasRole/hasPermission, bukan user_type."
        FAILED_REGEX=1
    fi

    # 3. Perbandingan string nama modul/role dalam logika IF/ELSE
    SLUG_HIT=$(echo "$ADDED" | grep -P '(==|===|!=|!==)' | grep -P "'(spmb|sikeu|siakad|simpeg|sinapra|sippm|lms|upm|admin|superadmin|mahasiswa|dosen|tendik|calon_mhs)'" | head -n 3)
    if [ -n "$SLUG_HIT" ]; then
        echo "❌ [Audit Zero Hardcode] Hardcode nama modul/role dalam logika di $file:"
        echo "$SLUG_HIT" | sed 's/^/    /'
        echo "   💡 Relasi/filter WAJIB memakai referensi ID entitas dari database."
        FAILED_REGEX=1
    fi
done <<< "$STAGED_FILES"

if [ $FAILED_REGEX -ne 0 ]; then
    echo "❌ [Audit Zero Hardcode & RBAC] DITOLAK pada tahap pemeriksaan statis!"
    exit 1
fi

# ------------------------------------------------------------------------------
# 2. DEEP AI AUDIT (Opencode Model Muse) — FULL DIFF
# ------------------------------------------------------------------------------
PROMPT_FILE=$(mktemp)

cat << 'EOF' > "$PROMPT_FILE"
Kamu adalah Code Auditor khusus Zero Hardcode & RBAC Backend (Laravel Strict Reviewer).
Periksa Git Diff berikut secara SANGAT KETAT terhadap aturan Zero Hardcode & RBAC Policy:

Aturan Baku (STRICT):
1. DILARANG MENYEDIAKAN VALIDASI ENUM STATIS: Jangan menggunakan aturan validasi seperti `in:REGULER,KARYAWAN` atau `in:Islam,Kristen` jika pilihan tersebut merepresentasikan data master referensi/dropdown dinamis. WAJIB menggunakan `exists:nama_tabel,id` (misalnya `exists:spmb_master_referensi,id` atau `exists:core_tipe_referensi,kode`). Pengecualian struktural HANYA order direction ('asc','desc') atau boolean ('true','false').
2. DILARANG PERBANDINGAN STATIS USER_TYPE: Dilarang membandingkan `$user->user_type` atau properti statis serupa dalam logika pengkondisian (if/else/switch/match). Otorisasi WAJIB melalui Gate, Policy, atau method RBAC (`hasRole()`, `hasPermission()`).
3. DILARANG HARDCODE NAMA MODUL / ROLE: Dilarang membandingkan string nama role/modul (seperti 'spmb', 'sikeu', 'admin', 'mahasiswa') dalam logika branching IF/ELSE untuk menentukan akses atau relasi. Relasi/filter wajib berbasis ID entitas atau permission.

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
- Jika kode bersih dan memenuhi aturan di atas, jawab TEPAT: PASSED
- Jika ditemukan pelanggaran pada baris baru (+), awali respon dengan REJECTED dan berikan rincian lengkap:
  * File & Potongan Baris Melanggar: (nama file dan baris/kode yang melanggar)
  * Aturan yang Dilanggar: (nama aturan / standar yang dilanggar)
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
    echo "❌ [Audit Zero Hardcode & RBAC] REJECTED: AI Reviewer gagal/timeout (Exit: $AI_EXIT_CODE)!"
    exit 1
fi

CLEAN_RESULT=$(echo "$RESULT" | sed -e '/^> build/d' -e '/^Loaded config/d' | awk '/./{p=1} p')

if echo "$RESULT" | grep -qi "REJECTED"; then
    echo "❌ [Audit Zero Hardcode & RBAC] REJECTED oleh AI (Muse)!"
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
    echo "✅ [Audit Zero Hardcode & RBAC] PASSED (Divalidasi oleh AI Opencode Muse)."
    exit 0
fi
