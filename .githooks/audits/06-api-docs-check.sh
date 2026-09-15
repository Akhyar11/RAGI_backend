#!/bin/bash
#
# Audit 7/9: API Documentation (deterministik, tanpa AI).
#  - REJECT: Controller BARU tanpa file docs/api/** yang ikut di-stage.
#  - WARN: Controller dimodifikasi tanpa perubahan docs.

echo "📚 [Audit 7/9: API Documentation] Memeriksa staged changes..."

NEW_CONTROLLERS=$(git diff --cached --name-only --diff-filter=A -- "app/Http/Controllers/**/*.php" "app/Http/Controllers/*.php")
MOD_CONTROLLERS=$(git diff --cached --name-only --diff-filter=M -- "app/Http/Controllers/**/*.php" "app/Http/Controllers/*.php")
STAGED_DOCS=$(git diff --cached --name-only -- "docs/**/*.md")

if [ -z "$NEW_CONTROLLERS" ] && [ -z "$MOD_CONTROLLERS" ]; then
    echo "ℹ️ [Audit API Documentation] Tidak ada perubahan controller. Skip."
    exit 0
fi

FAILED=0

if [ -n "$NEW_CONTROLLERS" ] && [ -z "$STAGED_DOCS" ]; then
    echo "❌ [Audit API Documentation] Controller baru tanpa dokumentasi:"
    echo "$NEW_CONTROLLERS" | sed 's/^/    /'
    echo "   💡 Setiap controller WAJIB punya docs/api/{Modul}/{Controller}.md (lihat .agent/skills/api_documentation/SKILL.md)."
    FAILED=1
fi

if [ -n "$MOD_CONTROLLERS" ] && [ -z "$STAGED_DOCS" ]; then
    echo "⚠️ [Audit API Documentation] Controller dimodifikasi tanpa update docs:"
    echo "$MOD_CONTROLLERS" | sed 's/^/    /'
    echo "   💡 Perbarui file docs/api/** yang relevan bila endpoint berubah."
fi

if [ $FAILED -ne 0 ]; then
    exit 1
fi

echo "✅ [Audit API Documentation] PASSED."
exit 0
