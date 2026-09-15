#!/bin/bash
#
# Audit 3/9: API CRUD Standard (deterministik, tanpa AI).
#  - REJECT: validasi langsung $request->validate() di Controller
#    (WAJIB Form Request: Store*Request / Update*Request).
#  - WARN: method index baru tanpa paginate().

echo "🤖 [Audit 3/9: API CRUD Standard] Memeriksa staged changes..."

STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM -- "app/Http/Controllers/**/*.php" "app/Http/Controllers/*.php")

if [ -z "$STAGED_FILES" ]; then
    echo "ℹ️ [Audit API CRUD Standard] Tidak ada perubahan controller yang di-stage. Skip."
    exit 0
fi

FAILED=0

while IFS= read -r file; do
    ADDED=$(git diff --cached -- "$file" | grep '^+' | grep -v '^+++' | sed 's^+^^')
    [ -z "$ADDED" ] && continue

    INLINE_VALIDATE=$(echo "$ADDED" | grep -n '\$request->validate(' | head -n 3)
    if [ -n "$INLINE_VALIDATE" ]; then
        echo "❌ [Audit API CRUD] Validasi inline \$request->validate() di $file:"
        echo "$INLINE_VALIDATE" | sed 's/^/    /'
        echo "   💡 WAJIB memakai Form Request terpisah (Store*Request / Update*Request di app/Http/Requests/)."
        FAILED=1
    fi

    if echo "$ADDED" | grep -qP 'function\s+index\s*\(' && [ -f "$file" ] && ! grep -q 'paginate(' "$file"; then
        echo "⚠️ [Audit API CRUD] Method index() baru di $file tanpa paginate(). Pastikan daftar memakai server-side pagination."
    fi
done <<< "$STAGED_FILES"

if [ $FAILED -ne 0 ]; then
    exit 1
fi

echo "✅ [Audit API CRUD Standard] PASSED."
exit 0
