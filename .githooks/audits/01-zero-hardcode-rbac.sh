#!/bin/bash
#
# Audit 2/9: Zero Hardcode & RBAC (deterministik, tanpa AI).
# Memeriksa baris baru (+) pada file PHP yang di-stage terhadap:
#  1. Validasi enum statis  -> wajib exists:nama_tabel,id (aturan: in:... berhuruf kapital)
#  2. Perbandingan user_type statis dalam logika (==, !=, in_array, match, case)
#  3. Perbandingan string nama modul/role statis dalam logika IF/ELSE
#
# Cakupan: app/, routes/. Dikecualikan: database/, tests/, docs, config.

echo "🤖 [Audit 2/9: Zero Hardcode & RBAC] Memeriksa staged changes..."

STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM -- "app/**/*.php" "app/*.php" "routes/*.php")

if [ -z "$STAGED_FILES" ]; then
    echo "ℹ️ [Audit Zero Hardcode & RBAC] Tidak ada file app/routes yang di-stage. Skip."
    exit 0
fi

FAILED=0

check_added_lines() {
    local file="$1"
    git diff --cached -- "$file" | grep '^+' | grep -v '^+++' | sed 's^+^^'
}

while IFS= read -r file; do
    ADDED=$(check_added_lines "$file")
    [ -z "$ADDED" ] && continue

    # 1. Validasi enum statis: in:XXX dengan huruf kapital (mis. in:REGULER,KARYAWAN / in:Islam,Kristen).
    #    Pengecualian struktural: asc, desc, true, false, angka.
    ENUM_HIT=$(echo "$ADDED" | grep -oP 'in:\K[A-Za-z0-9_|,\s.-]+' | grep -P '([A-Z]{2,}|[A-Z][a-z]{2,})' | head -n 3)
    if [ -n "$ENUM_HIT" ]; then
        echo "❌ [Audit Zero Hardcode] Validasi enum statis di $file:"
        echo "$ENUM_HIT" | sed 's/^/    in:/'
        echo "   💡 Validasi dropdown/master WAJIB memakai exists:nama_tabel,id (bukan in:STATIS)."
        FAILED=1
    fi

    # 2. Perbandingan user_type statis dalam logika.
    USERTYPE_HIT=$(echo "$ADDED" | grep -P '(==|===|!=|!==|in_array|match\s*\(|^\s*case\s)' | grep -P 'user.type' | head -n 3)
    if [ -n "$USERTYPE_HIT" ]; then
        echo "❌ [Audit Zero Hardcode] Perbandingan user_type statis di $file:"
        echo "$USERTYPE_HIT" | sed 's/^/    /'
        echo "   💡 Otorisasi WAJIB via Policy/Gate atau hasRole/hasPermission, bukan user_type."
        FAILED=1
    fi

    # 3. Perbandingan string nama modul/role dalam logika IF/ELSE.
    SLUG_HIT=$(echo "$ADDED" | grep -P '(==|===|!=|!==)' | grep -P "'(spmb|sikeu|siakad|simpeg|sinapra|sippm|lms|upm|admin|superadmin|mahasiswa|dosen|tendik|calon_mhs)'" | head -n 3)
    if [ -n "$SLUG_HIT" ]; then
        echo "❌ [Audit Zero Hardcode] Hardcode nama modul/role dalam logika di $file:"
        echo "$SLUG_HIT" | sed 's/^/    /'
        echo "   💡 Relasi/filter WAJIB memakai referensi ID entitas dari database."
        FAILED=1
    fi
done <<< "$STAGED_FILES"

if [ $FAILED -ne 0 ]; then
    exit 1
fi

echo "✅ [Audit Zero Hardcode & RBAC] PASSED."
exit 0
