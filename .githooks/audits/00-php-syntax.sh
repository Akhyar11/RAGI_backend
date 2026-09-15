#!/bin/bash

echo "🔍 [Audit 1/9: PHP Syntax] Memeriksa sintaks file PHP yang di-stage..."

STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM -- "*.php")

if [ -z "$STAGED_FILES" ]; then
    echo "ℹ️ [Audit PHP Syntax] Tidak ada file PHP yang di-stage. Skip."
    exit 0
fi

if ! command -v php &> /dev/null; then
    echo "⚠️ [Audit PHP Syntax] Binary php tidak ditemukan, dilewati."
    exit 0
fi

FAILED=0
while IFS= read -r file; do
    [ -f "$file" ] || continue
    OUTPUT=$(php -l "$file" 2>&1)
    if [ $? -ne 0 ]; then
        echo "❌ [Audit PHP Syntax] Sintaks error di $file:"
        echo "$OUTPUT"
        FAILED=1
    fi
done <<< "$STAGED_FILES"

if [ $FAILED -ne 0 ]; then
    exit 1
fi

echo "✅ [Audit PHP Syntax] PASSED."
exit 0
