#!/bin/bash
#
# Audit 4/9: API Hygiene (deterministik, tanpa AI).
#  - REJECT: sisa debug dd()/dump()/die()/exit() pada baris baru.
#  - REJECT: pemanggilan env() di app/ (konfigurasi WAJIB via config/ atau SystemSetting).

echo "🧹 [Audit 4/9: API Hygiene] Memeriksa staged changes..."

STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM -- "app/**/*.php" "app/*.php")

if [ -z "$STAGED_FILES" ]; then
    echo "ℹ️ [Audit API Hygiene] Tidak ada file app/ yang di-stage. Skip."
    exit 0
fi

FAILED=0

while IFS= read -r file; do
    ADDED=$(git diff --cached -- "$file" | grep '^+' | grep -v '^+++' | sed 's^+^^')
    [ -z "$ADDED" ] && continue

    DEBUG_HIT=$(echo "$ADDED" | grep -nP '(?<!\w)(dd|dump|die|exit)\s*\(' | head -n 3)
    if [ -n "$DEBUG_HIT" ]; then
        echo "❌ [Audit API Hygiene] Sisa debug di $file:"
        echo "$DEBUG_HIT" | sed 's/^/    /'
        echo "   💡 Hapus dd()/dump()/die()/exit() sebelum commit."
        FAILED=1
    fi

    ENV_HIT=$(echo "$ADDED" | grep -nP '(?<!\w)env\s*\(' | head -n 3)
    if [ -n "$ENV_HIT" ]; then
        echo "❌ [Audit API Hygiene] Pemanggilan env() di $file:"
        echo "$ENV_HIT" | sed 's/^/    /'
        echo "   💡 Konfigurasi WAJIB via config/ atau SystemSetting (DB), bukan env() langsung di app/."
        FAILED=1
    fi
done <<< "$STAGED_FILES"

if [ $FAILED -ne 0 ]; then
    exit 1
fi

echo "✅ [Audit API Hygiene] PASSED."
exit 0
