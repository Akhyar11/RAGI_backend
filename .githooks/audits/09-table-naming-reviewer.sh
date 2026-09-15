#!/bin/bash
#
# Audit 9/9: Table Naming Standard (deterministik, tanpa AI).
# Tabel baru (Schema::create) dan property $table pada Model WAJIB memakai
# prefix modul (core_, iam_, spmb_, siakad_, sikeu_, simpeg_, sinapra_,
# sippm_, lms_, oauth_, sso_), kecuali tabel bawaan framework Laravel.
# Foreign key constrained('tabel') WAJIB mengarah ke tabel berprefix.

echo "🗄️ [Audit 9/9: Table Naming Standard] Memeriksa staged changes..."

STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM -- "database/migrations/*.php" "app/Models/**/*.php" "app/Models/*.php")

if [ -z "$STAGED_FILES" ]; then
    echo "ℹ️ [Audit Table Naming Standard] Tidak ada perubahan migration atau model yang di-stage. Skip."
    exit 0
fi

PREFIX_RE='^(core|iam|spmb|siakad|sikeu|simpeg|sinapra|sippm|lms|oauth|sso)_'
FRAMEWORK_TABLES='^(sessions|cache|jobs|failed_jobs|job_batches|password_resets|password_reset_tokens)$'

is_valid_table() {
    local t="$1"
    echo "$t" | grep -qP "$PREFIX_RE" && return 0
    echo "$t" | grep -qP "$FRAMEWORK_TABLES" && return 0
    return 1
}

FAILED=0

while IFS= read -r file; do
    ADDED=$(git diff --cached -- "$file" | grep '^+' | grep -v '^+++' | sed 's^+^^')
    [ -z "$ADDED" ] && continue

    NEW_TABLES=$(echo "$ADDED" | grep -oP "Schema::create\(\s*'\K[^']+" | head -n 10)
    for t in $NEW_TABLES; do
        if ! is_valid_table "$t"; then
            echo "❌ [Audit Table Naming] Tabel '$t' di $file tanpa prefix modul."
            echo "   💡 Gunakan prefix modul (core_, spmb_, siakad_, sikeu_, simpeg_, sinapra_, sippm_, iam_, ...)."
            FAILED=1
        fi
    done

    MODEL_TABLES=$(echo "$ADDED" | grep -oP "protected \\\$table\s*=\s*'\K[^']+" | head -n 10)
    for t in $MODEL_TABLES; do
        if ! is_valid_table "$t"; then
            echo "❌ [Audit Table Naming] property \$table = '$t' di $file tanpa prefix modul."
            FAILED=1
        fi
    done

    FK_TABLES=$(echo "$ADDED" | grep -oP "constrained\(\s*'\K[^']+" | head -n 10)
    for t in $FK_TABLES; do
        if ! is_valid_table "$t"; then
            echo "❌ [Audit Table Naming] constrained('$t') di $file mengarah ke tabel tanpa prefix modul."
            FAILED=1
        fi
    done
done <<< "$STAGED_FILES"

if [ $FAILED -ne 0 ]; then
    exit 1
fi

echo "✅ [Audit Table Naming Standard] PASSED."
exit 0
